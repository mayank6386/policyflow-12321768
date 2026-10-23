<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use App\Services\MockInsurerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MockInsurerController extends Controller
{
    public function __construct(protected MockInsurerService $mockInsurer)
    {
    }

    public function callback(Request $request)
    {
        $payload = $request->validate([
            'provider_reference' => ['required', 'string'],
            'event_id' => ['required', 'string'],
            'policy_number' => ['required', 'string'],
        ]);

        $result = $this->mockInsurer->callback($payload);

        return response()->json($result, $result['accepted'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    public function setScenario(Request $request)
    {
        if (! config('policyflow.mock_insurer_enabled')) {
            abort(403, 'Mock insurer scenario controls are disabled in production.');
        }

        $scenario = $request->validate([
            'scenario' => ['required', 'string', Rule::in(config('policyflow.allowed_scenarios'))],
        ])['scenario'];

        config()->set('policyflow.mock_insurer_scenario', $scenario);
        Cache::forever('policyflow.mock_insurer_scenario', $scenario);

        return response()->json([
            'message' => 'Mock insurer scenario updated.',
            'scenario' => $scenario,
        ]);
    }

    public function getScenario()
    {
        return response()->json([
            'scenario' => Cache::get('policyflow.mock_insurer_scenario', config('policyflow.mock_insurer_scenario', 'success')),
        ]);
    }

    public function reconcile(Request $request)
    {
        $providerReference = $request->validate([
            'provider_reference' => ['required', 'string'],
        ])['provider_reference'];

        $policy = PolicyRequest::where('provider_reference', $providerReference)->firstOrFail();
        $reconciled = $this->mockInsurer->reconcile($policy);

        return response()->json([
            'reconciled' => $reconciled,
            'data' => $policy->fresh(),
        ]);
    }
}
