<?php

namespace App\Models;

use App\Enums\PolicyState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    protected $table = 'policy_requests';

    protected $fillable = [
        'public_request_id',
        'agent_id',
        'client_request_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'premium_snapshot',
        'status',
        'provider_reference',
        'provider_policy_number',
        'last_error',
        'issue_attempt_count',
        'last_provider_response',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'trip_start' => 'date',
        'trip_end' => 'date',
        'premium_snapshot' => 'decimal:2',
        'last_provider_response' => 'array',
    ];

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class, 'policy_request_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class, 'policy_request_id');
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class, 'policy_request_id');
    }

    public function isIssued(): bool
    {
        return $this->status === PolicyState::ISSUED->value;
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
