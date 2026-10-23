<?php

namespace App\Services;

use App\Enums\PolicyState;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use Illuminate\Support\Facades\DB;

class PolicySubmissionService
{
    public function __construct(
        protected PolicyWorkflow $workflow,
        protected MockInsurerService $mockInsurer
    ) {
    }

    public function submit(PolicyRequest $policy): PolicyRequest
    {
        if ($policy->status === PolicyState::ISSUED->value) {
            return $policy;
        }

        return DB::transaction(function () use ($policy) {
            $policy->refresh();

            if ($policy->status === PolicyState::ISSUED->value) {
                return $policy;
            }

            $this->workflow->transition($policy, PolicyState::SUBMITTED->value, 'submission_service', 'Policy request accepted for insurer submission.');
            $policy->issue_attempt_count = $policy->issue_attempt_count + 1;
            $policy->save();

            return $this->mockInsurer->submit($policy);
        });
    }
}
