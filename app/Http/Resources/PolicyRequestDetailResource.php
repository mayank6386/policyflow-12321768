<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->public_request_id,
            'agent_id' => $this->agent_id,
            'client_request_id' => $this->client_request_id,
            'customer_name' => $this->customer_name,
            'dob' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'plan_code' => $this->plan_code,
            'sum_insured' => (int) $this->sum_insured,
            'premium_snapshot' => (string) $this->premium_snapshot,
            'status' => $this->status,
            'provider' => [
                'reference' => $this->provider_reference,
                'policy_number' => $this->provider_policy_number,
                'last_response' => $this->last_provider_response,
            ],
            'attempts' => $this->attempts()->orderBy('attempt_number')->get()->map(function ($attempt) {
                return [
                    'attempt_number' => (int) $attempt->attempt_number,
                    'status' => $attempt->status,
                    'provider_reference' => $attempt->provider_reference,
                    'error_message' => $attempt->error_message,
                    'request_payload' => $attempt->request_payload,
                    'response_payload' => $attempt->response_payload,
                    'created_at' => $attempt->created_at?->toISOString(),
                ];
            })->all(),
            'state_history' => $this->stateHistory()->orderBy('id')->get()->map(function ($history) {
                return [
                    'from_state' => $history->from_state,
                    'to_state' => $history->to_state,
                    'source' => $history->source,
                    'reason' => $history->reason,
                    'created_at' => $history->created_at?->toISOString(),
                ];
            })->all(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
