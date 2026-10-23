<?php

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;

class PolicyWorkflow
{
    public const ALLOWED_TRANSITIONS = [
        PolicyState::RECEIVED->value => [PolicyState::VALIDATED->value],
        PolicyState::VALIDATED->value => [PolicyState::SUBMITTED->value, PolicyState::FAILED->value],
        PolicyState::SUBMITTED->value => [PolicyState::PENDING_CONFIRMATION->value, PolicyState::FAILED->value, PolicyState::NEEDS_RECONCILIATION->value],
        PolicyState::PENDING_CONFIRMATION->value => [PolicyState::ISSUED->value, PolicyState::NEEDS_RECONCILIATION->value, PolicyState::FAILED->value],
        PolicyState::FAILED->value => [PolicyState::SUBMITTED->value, PolicyState::VALIDATED->value],
        PolicyState::NEEDS_RECONCILIATION->value => [PolicyState::ISSUED->value, PolicyState::FAILED->value, PolicyState::SUBMITTED->value],
        PolicyState::ISSUED->value => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        if ($from === PolicyState::ISSUED->value) {
            return false;
        }

        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public function transition(PolicyRequest $policy, string $newState, string $source, string $reason = ''): PolicyRequest
    {
        $fromState = $policy->status;

        if (! $this->canTransition($fromState, $newState)) {
            throw new \InvalidArgumentException("Invalid state transition from {$fromState} to {$newState}.");
        }

        return DB::transaction(function () use ($policy, $newState, $source, $reason, $fromState) {
            $policy->status = $newState;
            $policy->save();

            PolicyStateHistory::create([
                'policy_request_id' => $policy->id,
                'from_state' => $fromState,
                'to_state' => $newState,
                'source' => $source,
                'reason' => $reason,
            ]);

            return $policy->fresh();
        });
    }
}
