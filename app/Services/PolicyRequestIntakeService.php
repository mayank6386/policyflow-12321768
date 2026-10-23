<?php

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PolicyRequestIntakeService
{
    public function __construct(protected PolicyWorkflow $workflow)
    {
    }

    public function createOrFetch(array $attributes): array
    {
        $dedupeKey = [
            'agent_id' => $attributes['agent_id'],
            'client_request_id' => $attributes['client_request_id'],
        ];

        $existing = PolicyRequest::where('agent_id', $dedupeKey['agent_id'])
            ->where('client_request_id', $dedupeKey['client_request_id'])
            ->first();

        if ($existing) {
            return [
                'policy' => $existing,
                'status' => 200,
                'message' => 'Existing request returned for matching idempotency key.',
            ];
        }

        try {
            return DB::transaction(function () use ($attributes) {
                $existing = PolicyRequest::where('agent_id', $attributes['agent_id'])
                    ->where('client_request_id', $attributes['client_request_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return [
                        'policy' => $existing,
                        'status' => 200,
                        'message' => 'Existing request returned for matching idempotency key.',
                    ];
                }

                $tripStart = Carbon::parse($attributes['trip_start']);
                $tripEnd = Carbon::parse($attributes['trip_end']);
                $premiumData = PremiumCalculator::calculate(
                    $attributes['date_of_birth'],
                    $tripStart->format('Y-m-d'),
                    $tripEnd->format('Y-m-d'),
                    $attributes['plan_code'],
                    $attributes['destination'],
                    (int) $attributes['sum_insured'],
                );

                $policy = PolicyRequest::create([
                    'public_request_id' => 'PRQ-'.strtoupper(bin2hex(random_bytes(6))),
                    'agent_id' => $attributes['agent_id'],
                    'client_request_id' => $attributes['client_request_id'],
                    'customer_name' => $attributes['customer_name'],
                    'date_of_birth' => $attributes['date_of_birth'],
                    'destination' => $attributes['destination'],
                    'trip_start' => $attributes['trip_start'],
                    'trip_end' => $attributes['trip_end'],
                    'plan_code' => $attributes['plan_code'],
                    'sum_insured' => $attributes['sum_insured'],
                    'premium_snapshot' => $premiumData['premium'],
                    'status' => PolicyState::RECEIVED->value,
                    'issue_attempt_count' => 0,
                ]);

                $this->workflow->transition($policy, PolicyState::VALIDATED->value, 'intake_service', 'Policy request validated and premium locked.');

                return [
                    'policy' => $policy->fresh(),
                    'status' => 201,
                    'message' => 'Policy request created successfully.',
                ];
            });
        } catch (QueryException $e) {
            $existing = PolicyRequest::where('agent_id', $attributes['agent_id'])
                ->where('client_request_id', $attributes['client_request_id'])
                ->first();

            if ($existing) {
                return [
                    'policy' => $existing,
                    'status' => 200,
                    'message' => 'Existing request returned after duplicate insert detection.',
                ];
            }

            throw $e;
        }
    }
}
