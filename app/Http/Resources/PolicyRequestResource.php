<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->public_request_id,
            'agent_id' => $this->agent_id,
            'client_request_id' => $this->client_request_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'plan_code' => $this->plan_code,
            'sum_insured' => (int) $this->sum_insured,
            'premium_snapshot' => (string) $this->premium_snapshot,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'provider_policy_number' => $this->provider_policy_number,
            'issue_attempt_count' => (int) $this->issue_attempt_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
