<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequestRequest;
use App\Http\Resources\PolicyRequestDetailResource;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\MockInsurerService;
use App\Services\PolicyRequestIntakeService;
use App\Services\PolicySubmissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyRequestIntakeService $intakeService,
        protected PolicySubmissionService $submissionService,
        protected MockInsurerService $mockInsurer
    ) {
    }

    public function store(StorePolicyRequestRequest $request)
    {
        $result = $this->intakeService->createOrFetch($request->validated());

        return response()->json([
            'message' => $result['message'],
            'data' => new PolicyRequestResource($result['policy']),
        ], $result['status']);
    }

    public function index(Request $request)
    {
        $query = PolicyRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        $results = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        return response()->json([
            'data' => PolicyRequestResource::collection($results),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }

    public function show(string $policyRequest)
    {
        $record = PolicyRequest::where('public_request_id', $policyRequest)
            ->orWhere('id', $policyRequest)
            ->firstOrFail();

        $record->load(['attempts', 'stateHistory', 'providerEvents']);

        return response()->json([
            'data' => new PolicyRequestDetailResource($record),
        ]);
    }

    public function submit(string $policyRequest)
    {
        $record = PolicyRequest::where('public_request_id', $policyRequest)
            ->orWhere('id', $policyRequest)
            ->firstOrFail();

        $policy = $this->submissionService->submit($record);

        return response()->json([
            'message' => 'Policy has been submitted to the mock insurer.',
            'data' => new PolicyRequestDetailResource($policy->fresh()),
        ], Response::HTTP_OK);
    }
}
