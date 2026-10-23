<?php

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Support\Facades\Cache;

class MockInsurerService
{
    public function __construct(protected PolicyWorkflow $workflow)
    {
    }

    public function submit(PolicyRequest $policy): PolicyRequest
    {
        $scenario = Cache::get('policyflow.mock_insurer_scenario', config('policyflow.mock_insurer_scenario', 'success'));

        if ($policy->status === PolicyState::ISSUED->value) {
            return $policy;
        }

        $attemptNumber = (int) $policy->issue_attempt_count;
        $providerReference = 'REF-'.strtoupper(bin2hex(random_bytes(6)));

        $record = IssuanceAttempt::create([
            'policy_request_id' => $policy->id,
            'attempt_number' => $attemptNumber,
            'provider_reference' => $providerReference,
            'request_payload' => [
                'policy_request_id' => $policy->public_request_id,
                'agent_id' => $policy->agent_id,
                'client_request_id' => $policy->client_request_id,
                'plan_code' => $policy->plan_code,
                'sum_insured' => (int) $policy->sum_insured,
                'premium_snapshot' => (string) $policy->premium_snapshot,
            ],
            'status' => 'started',
        ]);

        $policy->provider_reference = $providerReference;
        $policy->save();

        return match ($scenario) {
            'success' => $this->handleSuccess($policy, $record),
            'temporary_failure' => $this->handleTemporaryFailure($policy, $record),
            'timeout_after_issue' => $this->handleTimeoutAfterIssue($policy, $record),
            'delayed_callback' => $this->handleDelayedCallback($policy, $record),
            'duplicate_callback' => $this->handleDuplicateCallback($policy, $record),
            default => throw new \UnexpectedValueException("Unsupported mock insurer scenario: {$scenario}."),
        };
    }

    public function reconcile(PolicyRequest $policy): bool
    {
        if ($policy->status === PolicyState::ISSUED->value) {
            return true;
        }

        $providerReference = $policy->provider_reference;
        if (! $providerReference) {
            return false;
        }

        $issuedProvider = ProviderEvent::where('provider_reference', $providerReference)
            ->where('status', 'issued')
            ->first();

        if (! $issuedProvider) {
            return false;
        }

        $policy->provider_policy_number = $issuedProvider->payload['policy_number'] ?? $policy->provider_policy_number;
        $policy->last_provider_response = $issuedProvider->payload;
        $policy->save();

        $this->workflow->transition(
            $policy,
            PolicyState::ISSUED->value,
            'reconciliation_service',
            'Provider issue event reconciled after an uncertain submission.',
        );

        return true;
    }

    public function callback(array $payload): array
    {
        $providerReference = $payload['provider_reference'] ?? null;
        $eventId = $payload['event_id'] ?? null;
        $policyNumber = $payload['policy_number'] ?? null;

        if (! $providerReference || ! $eventId || ! $policyNumber) {
            return ['accepted' => false, 'message' => 'provider_reference, event_id and policy_number are required.'];
        }

        $duplicate = ProviderEvent::where('provider_event_id', $eventId)->first();
        if ($duplicate) {
            return ['accepted' => true, 'duplicate' => true, 'message' => 'Duplicate callback ignored.'];
        }

        $policy = PolicyRequest::where('provider_reference', $providerReference)->first();
        if (! $policy) {
            return ['accepted' => false, 'message' => 'No matching policy reference found.'];
        }

        $event = ProviderEvent::create([
            'policy_request_id' => $policy->id,
            'provider_reference' => $providerReference,
            'provider_event_id' => $eventId,
            'event_type' => 'policy_issued',
            'status' => 'issued',
            'payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $providerReference,
                'event_id' => $eventId,
            ],
        ]);

        if ($policy->status === PolicyState::ISSUED->value) {
            return ['accepted' => true, 'duplicate' => true, 'message' => 'Policy already issued.'];
        }

        $policy->provider_policy_number = $policyNumber;
        $policy->last_provider_response = $event->payload;
        $policy->provider_reference = $providerReference;
        $policy->save();

        $this->workflow->transition(
            $policy,
            PolicyState::ISSUED->value,
            'mock_insurer_callback',
            'Provider callback confirmed policy issuance.',
        );

        return ['accepted' => true, 'duplicate' => false, 'message' => 'Provider callback processed successfully.'];
    }

    protected function handleSuccess(PolicyRequest $policy, IssuanceAttempt $record): PolicyRequest
    {
        $policyNumber = 'POL-'.strtoupper(bin2hex(random_bytes(4)));

        $record->update([
            'status' => 'issued',
            'response_payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $policy->provider_reference,
                'status' => 'issued',
            ],
        ]);

        $policy->provider_policy_number = $policyNumber;
        $policy->last_provider_response = $record->response_payload;
        $policy->save();

        ProviderEvent::create([
            'policy_request_id' => $policy->id,
            'provider_reference' => $policy->provider_reference,
            'provider_event_id' => 'EV-'.strtoupper(bin2hex(random_bytes(4))),
            'event_type' => 'policy_issued',
            'status' => 'issued',
            'payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $policy->provider_reference,
            ],
        ]);

        $this->workflow->transition(
            $policy,
            PolicyState::PENDING_CONFIRMATION->value,
            'mock_insurer',
            'Provider accepted the request and returned an issuance confirmation.',
        );
        $this->workflow->transition(
            $policy->fresh(),
            PolicyState::ISSUED->value,
            'mock_insurer',
            'Mock insurer confirmed policy issuance.',
        );

        return $policy->fresh();
    }

    protected function handleTemporaryFailure(PolicyRequest $policy, IssuanceAttempt $record): PolicyRequest
    {
        $record->update([
            'status' => 'failed',
            'error_message' => 'Temporary provider outage. Retry allowed.',
            'response_payload' => ['status' => 'temporary_failure'],
        ]);

        $policy->last_error = 'Temporary provider outage. Retry allowed.';
        $policy->save();
        $this->workflow->transition(
            $policy,
            PolicyState::FAILED->value,
            'mock_insurer',
            'Temporary provider outage. Retry allowed.',
        );

        return $policy->fresh();
    }

    protected function handleTimeoutAfterIssue(PolicyRequest $policy, IssuanceAttempt $record): PolicyRequest
    {
        $policyNumber = 'POL-'.strtoupper(bin2hex(random_bytes(4)));
        $record->update([
            'status' => 'timeout_after_issue',
            'response_payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $policy->provider_reference,
                'status' => 'issued',
                'timed_out' => true,
            ],
        ]);

        $policy->provider_policy_number = $policyNumber;
        $policy->last_provider_response = $record->response_payload;
        $policy->save();
        $this->workflow->transition(
            $policy,
            PolicyState::PENDING_CONFIRMATION->value,
            'mock_insurer',
            'Provider issued the policy but the submission response timed out.',
        );

        ProviderEvent::create([
            'policy_request_id' => $policy->id,
            'provider_reference' => $policy->provider_reference,
            'provider_event_id' => 'EV-'.strtoupper(bin2hex(random_bytes(4))),
            'event_type' => 'provider_recorded_issue',
            'status' => 'issued',
            'payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $policy->provider_reference,
            ],
        ]);

        return $policy->fresh();
    }

    protected function handleDelayedCallback(PolicyRequest $policy, IssuanceAttempt $record): PolicyRequest
    {
        $record->update([
            'status' => 'pending_confirmation',
            'response_payload' => ['status' => 'pending_confirmation'],
        ]);

        $policy->last_provider_response = $record->response_payload;
        $policy->save();
        $this->workflow->transition(
            $policy,
            PolicyState::PENDING_CONFIRMATION->value,
            'mock_insurer',
            'Provider confirmation is delayed.',
        );

        return $policy->fresh();
    }

    protected function handleDuplicateCallback(PolicyRequest $policy, IssuanceAttempt $record): PolicyRequest
    {
        $policyNumber = 'POL-'.strtoupper(bin2hex(random_bytes(4)));
        $eventId = 'EV-'.strtoupper(bin2hex(random_bytes(4)));
        $record->update([
            'status' => 'issued',
            'response_payload' => [
                'policy_number' => $policyNumber,
                'provider_reference' => $policy->provider_reference,
                'status' => 'issued',
            ],
        ]);

        $callback = [
            'provider_reference' => $policy->provider_reference,
            'event_id' => $eventId,
            'policy_number' => $policyNumber,
        ];
        $this->workflow->transition(
            $policy,
            PolicyState::PENDING_CONFIRMATION->value,
            'mock_insurer',
            'Provider confirmation is pending callback processing.',
        );
        $result = $this->callback($callback);
        if (! $result['accepted']) {
            throw new \RuntimeException('Mock insurer callback was rejected: '.$result['message']);
        }

        $duplicate = $this->callback($callback);
        if (! $duplicate['accepted'] || ! $duplicate['duplicate']) {
            throw new \RuntimeException('Duplicate mock insurer callback was not safely deduplicated.');
        }

        return $policy->fresh();
    }
}
