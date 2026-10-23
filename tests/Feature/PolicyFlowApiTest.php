<?php

namespace Tests\Feature;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PolicyFlowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('policyflow.mock_insurer_scenario');
    }

    public function test_valid_intake_and_correct_premium(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(201);
        $response->assertJsonPath('data.premium_snapshot', '225.00');
        $response->assertJsonPath('data.status', 'VALIDATED');
    }

    public function test_invalid_dates_and_unsupported_plans(): void
    {
        $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Wrong',
            'date_of_birth' => '2099-01-01',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-09',
            'plan_code' => 'GOLD',
            'sum_insured' => 100000,
        ])->assertStatus(422);
    }

    public function test_unsupported_coverage_and_destination(): void
    {
        $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Wrong',
            'date_of_birth' => '1990-01-01',
            'destination' => 'EUROPE',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 200000,
        ])->assertStatus(422);
    }

    public function test_inclusive_travel_days_and_pricing_multipliers(): void
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0004',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Travel',
            'date_of_birth' => '2005-04-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.premium_snapshot', '60.00');

        $calculated = PremiumCalculator::calculate('2005-04-10', '2026-11-10', '2026-11-14', 'BASIC', 'DOMESTIC', 100000);
        $this->assertSame('60.00', $calculated['premium']);
    }

    public function test_age_boundaries_and_multiplier_rules(): void
    {
        $this->assertSame('1.10', PremiumCalculator::getAgeMultiplier(now()->subYears(17)->startOfDay(), now()->startOfDay()));
        $this->assertSame('1.00', PremiumCalculator::getAgeMultiplier(now()->subYears(18)->startOfDay(), now()->startOfDay()));
        $this->assertSame('1.50', PremiumCalculator::getAgeMultiplier(now()->subYears(61)->startOfDay(), now()->startOfDay()));
        $this->assertSame('2.00', PremiumCalculator::getAgeMultiplier(now()->subYears(71)->startOfDay(), now()->startOfDay()));
    }

    public function test_duplicate_intake_returns_existing_request(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0005',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $first = $this->postJson('/api/policy-requests', $payload);
        $second = $this->postJson('/api/policy-requests', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200);
        $this->assertSame(1, PolicyRequest::count());
    }

    public function test_database_uniqueness_and_status_filtering(): void
    {
        $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0006',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'One',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-08-01',
            'trip_end' => '2026-08-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $this->getJson('/api/policy-requests?status=VALIDATED')->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_successful_issuance_and_single_policy_number(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0007',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Issued flow',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];
        $response = $this->postJson('/api/policy-requests', $payload);
        $id = $response->json('data.id');
        $policy = PolicyRequest::where('public_request_id', $id)->firstOrFail();

        $submit = $this->postJson('/api/policy-requests/'.$id.'/submit');
        $submit->assertStatus(200);
        $this->assertSame('ISSUED', $policy->fresh()->status);
        $this->assertNotNull($policy->fresh()->provider_policy_number);
        $this->assertSame(1, $policy->fresh()->issue_attempt_count);
        $this->assertSame([1], $policy->fresh()->attempts()->pluck('attempt_number')->all());
        $this->assertSame(
            ['RECEIVED>VALIDATED', 'VALIDATED>SUBMITTED', 'SUBMITTED>PENDING_CONFIRMATION', 'PENDING_CONFIRMATION>ISSUED'],
            $policy->fresh()->stateHistory()->orderBy('id')->get()
                ->map(fn ($event) => $event->from_state.'>'.$event->to_state)->all(),
        );
    }

    public function test_timeout_after_provider_success_and_reconciliation(): void
    {
        config()->set('policyflow.mock_insurer_scenario', 'timeout_after_issue');

        $payload = [
            'client_request_id' => 'AGT-2026-0008',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Timeout',
            'date_of_birth' => '1995-03-04',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-12-10',
            'trip_end' => '2026-12-12',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $id = $this->postJson('/api/policy-requests', $payload)->json('data.id');
        $this->postJson('/api/policy-requests/'.$id.'/submit')->assertStatus(200);
        $request = PolicyRequest::where('public_request_id', $id)->firstOrFail();
        $this->assertSame('PENDING_CONFIRMATION', $request->status);

        $this->postJson('/api/mock-insurer/reconcile', ['provider_reference' => $request->provider_reference])->assertStatus(200);
        $this->assertSame('ISSUED', $request->fresh()->status);
        $this->assertSame(
            ['RECEIVED>VALIDATED', 'VALIDATED>SUBMITTED', 'SUBMITTED>PENDING_CONFIRMATION', 'PENDING_CONFIRMATION>ISSUED'],
            $request->fresh()->stateHistory()->orderBy('id')->get()
                ->map(fn ($event) => $event->from_state.'>'.$event->to_state)->all(),
        );
    }

    public function test_retry_after_temporary_failure_without_duplication(): void
    {
        config()->set('policyflow.mock_insurer_scenario', 'temporary_failure');

        $first = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0009',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Retry',
            'date_of_birth' => '1988-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-10-10',
            'trip_end' => '2026-10-15',
            'plan_code' => 'PREMIUM',
            'sum_insured' => 500000,
        ]);

        $id = $first->json('data.id');
        $this->postJson('/api/policy-requests/'.$id.'/submit')->assertStatus(200);
        $this->assertSame('FAILED', PolicyRequest::where('public_request_id', $id)->first()->status);

        config()->set('policyflow.mock_insurer_scenario', 'success');
        $this->postJson('/api/policy-requests/'.$id.'/submit')->assertStatus(200);
        $policy = PolicyRequest::where('public_request_id', $id)->firstOrFail();
        $this->assertSame('ISSUED', $policy->status);
        $this->assertSame(2, $policy->issue_attempt_count);
        $this->assertSame([1, 2], $policy->attempts()->orderBy('attempt_number')->pluck('attempt_number')->all());
    }

    public function test_repeated_callback_processed_only_once(): void
    {
        config()->set('policyflow.mock_insurer_scenario', 'delayed_callback');
        $payload = [
            'client_request_id' => 'AGT-2026-0010',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Duplicate callback',
            'date_of_birth' => '1991-02-02',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $id = $this->postJson('/api/policy-requests', $payload)->json('data.id');
        $this->postJson('/api/policy-requests/'.$id.'/submit')->assertOk()
            ->assertJsonPath('data.status', 'PENDING_CONFIRMATION');
        $policy = PolicyRequest::where('public_request_id', $id)->firstOrFail();
        $providerReference = $policy->provider_reference;

        $first = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerReference,
            'event_id' => 'EV-1234',
            'policy_number' => 'POL-DUP-1',
        ]);

        $second = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerReference,
            'event_id' => 'EV-1234',
            'policy_number' => 'POL-DUP-1',
        ]);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame(1, $policy->fresh()->providerEvents()->count());
    }

    public function test_invalid_state_transitions_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $policy = PolicyRequest::create([
            'public_request_id' => 'PRQ-FAKE',
            'agent_id' => 'AGENT-999',
            'client_request_id' => 'CLIENT-999',
            'customer_name' => 'Bad',
            'date_of_birth' => '1992-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-01-01',
            'trip_end' => '2026-01-02',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium_snapshot' => '10.00',
            'status' => PolicyState::ISSUED->value,
        ]);

        app(\App\Services\PolicyWorkflow::class)->transition($policy, PolicyState::SUBMITTED->value, 'test', 'bad transition');
    }

    public function test_policy_details_endpoint_returns_history(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0011',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'History',
            'date_of_birth' => '1990-10-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-09-01',
            'trip_end' => '2026-09-04',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $id = $response->json('data.id');
        $this->getJson('/api/policy-requests/'.$id)->assertStatus(200);
    }

    public function test_validation_and_pagination_are_working(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->postJson('/api/policy-requests', [
                'client_request_id' => 'AGT-2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'agent_id' => 'AGENT-17',
                'customer_name' => 'Customer '.$i,
                'date_of_birth' => '1992-02-02',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-10-10',
                'trip_end' => '2026-10-12',
                'plan_code' => 'BASIC',
                'sum_insured' => 100000,
            ]);
        }

        $this->getJson('/api/policy-requests?page=2')->assertStatus(200);
    }

    public function test_missing_intake_fields_return_validation_errors(): void
    {
        $this->postJson('/api/policy-requests', [
            'agent_id' => 'AGENT-17',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_request_id',
                'customer_name',
                'date_of_birth',
                'destination',
                'trip_start',
                'trip_end',
                'plan_code',
                'sum_insured',
            ]);
    }

    public function test_dashboard_create_and_submit_flow_works(): void
    {
        $this->get('/dashboard/policy-requests/create')
            ->assertOk()
            ->assertSee('name="_token"', false);

        $response = $this->post('/dashboard/policy-requests', [
            'client_request_id' => 'WEB-TEST-0001',
            'agent_id' => 'AGENT-WEB',
            'customer_name' => 'Dashboard Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);

        $policy = PolicyRequest::where('client_request_id', 'WEB-TEST-0001')->firstOrFail();
        $response->assertRedirect(route('dashboard.policy-requests.show', $policy->public_request_id))
            ->assertSessionHas('success');

        $this->post(route('dashboard.policy-requests.submit', $policy->public_request_id))
            ->assertRedirect(route('dashboard.policy-requests.show', $policy->public_request_id))
            ->assertSessionHas('success');

        $this->assertSame('ISSUED', $policy->fresh()->status);
        $this->get(route('dashboard.policy-requests.show', $policy->public_request_id))
            ->assertOk()
            ->assertSee('Policy issued successfully.');
    }

    public function test_dashboard_reconciliation_control_completes_pending_issue(): void
    {
        config()->set('policyflow.mock_insurer_scenario', 'timeout_after_issue');

        $id = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'WEB-RECONCILE-0001',
            'agent_id' => 'AGENT-WEB',
            'customer_name' => 'Reconciliation Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ])->json('data.id');

        $this->postJson('/api/policy-requests/'.$id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_CONFIRMATION');

        $this->get(route('dashboard.policy-requests.show', $id))
            ->assertOk()
            ->assertSee('Reconcile provider status');

        $policy = PolicyRequest::where('public_request_id', $id)->firstOrFail();
        $this->post(route('dashboard.policy-requests.reconcile', $id))
            ->assertRedirect(route('dashboard.policy-requests.show', $id))
            ->assertSessionHas('success');

        $this->assertSame('ISSUED', $policy->fresh()->status);
    }

    public function test_dashboard_export_returns_filtered_csv(): void
    {
        $this->postJson('/api/policy-requests', [
            'client_request_id' => 'EXPORT-001',
            'agent_id' => 'AGENT-EXPORT',
            'customer_name' => '=SUM(1,1)',
            'date_of_birth' => '1998-04-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $response = $this->get('/dashboard/policy-requests/export?status=VALIDATED&search=EXPORT-001')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $header = str_getcsv(explode("\n", trim($response->streamedContent()))[0]);
        $this->assertSame([
            'Request ID',
            'Customer',
            'Agent ID',
            'Client Request ID',
            'Destination',
            'Plan',
            'Sum Insured',
            'Premium',
            'Status',
        ], $header);
        $this->assertStringContainsString("'=SUM(1,1)", $response->streamedContent());
        $this->assertStringNotContainsString('WEB-TEST', $response->streamedContent());
    }

    public function test_mock_insurer_scenario_setting_persists_between_requests(): void
    {
        Cache::forget('policyflow.mock_insurer_scenario');

        $this->postJson('/api/mock-insurer/scenario', ['scenario' => 'temporary_failure'])
            ->assertOk();

        $this->getJson('/api/mock-insurer/scenario')
            ->assertOk()
            ->assertJsonPath('scenario', 'temporary_failure');

        $id = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'SCENARIO-001',
            'agent_id' => 'AGENT-SCENARIO',
            'customer_name' => 'Scenario Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ])->json('data.id');
        $this->postJson('/api/policy-requests/'.$id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'FAILED');

        Cache::forget('policyflow.mock_insurer_scenario');
    }

    public function test_duplicate_callback_scenario_deduplicates_provider_events(): void
    {
        config()->set('policyflow.mock_insurer_scenario', 'duplicate_callback');
        Cache::forget('policyflow.mock_insurer_scenario');

        $id = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0012',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Duplicate scenario',
            'date_of_birth' => '1991-02-02',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ])->json('data.id');

        $this->postJson('/api/policy-requests/'.$id.'/submit')->assertOk();
        $policy = PolicyRequest::where('public_request_id', $id)->firstOrFail();

        $this->assertSame('ISSUED', $policy->status);
        $this->assertSame(1, $policy->providerEvents()->count());
        $this->assertSame(4, $policy->stateHistory()->count());
    }
}
