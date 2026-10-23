<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderEvent extends Model
{
    protected $table = 'provider_events';

    protected $fillable = [
        'policy_request_id',
        'provider_reference',
        'provider_event_id',
        'event_type',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class, 'policy_request_id');
    }
}
