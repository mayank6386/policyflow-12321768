<?php

namespace App\Http\Controllers\Web;

use App\Enums\PolicyState;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequestRequest;
use App\Models\PolicyRequest;
use App\Services\MockInsurerService;
use App\Services\PolicyRequestIntakeService;
use App\Services\PolicySubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PolicyRequestDashboardController extends Controller
{
    public function __construct(
        protected PolicyRequestIntakeService $intakeService,
        protected PolicySubmissionService $submissionService,
        protected MockInsurerService $mockInsurer,
    ) {
    }

    public function index(Request $request)
    {
        if (! Schema::hasTable('policy_requests')) {
            return view('dashboard.policy-requests.index', [
                'requests' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedStatus' => $request->status,
                'search' => $request->search,
            ]);
        }

        $query = $this->filteredRequestsQuery($request);
        $requests = $query->orderByDesc('created_at')->paginate(10)->appends($request->query());

        return view('dashboard.policy-requests.index', [
            'requests' => $requests,
            'selectedStatus' => $request->status,
            'search' => $request->search,
        ]);
    }

    public function create()
    {
        return view('dashboard.policy-requests.create', [
            'clientRequestId' => 'WEB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
        ]);
    }

    public function store(StorePolicyRequestRequest $request)
    {
        $result = $this->intakeService->createOrFetch($request->validated());

        return redirect()->route('dashboard.policy-requests.show', ['policyRequest' => $result['policy']->public_request_id])
            ->with('success', $result['message']);
    }

    public function submit(string $policyRequest)
    {
        $record = $this->findPolicyRequest($policyRequest);
        $policy = $this->submissionService->submit($record);

        $message = match ($policy->status) {
            PolicyState::ISSUED->value => 'Policy issued successfully.',
            PolicyState::PENDING_CONFIRMATION->value => 'Submission recorded. Provider confirmation is pending.',
            PolicyState::FAILED->value => $policy->last_error ?? 'The insurer submission failed. You can retry.',
            default => 'Policy submission completed with status '.$policy->status.'.',
        };

        return redirect()->route('dashboard.policy-requests.show', ['policyRequest' => $policy->public_request_id])
            ->with($policy->status === PolicyState::FAILED->value ? 'error' : 'success', $message);
    }

    public function reconcile(string $policyRequest)
    {
        $record = $this->findPolicyRequest($policyRequest);

        if (! $this->mockInsurer->reconcile($record)) {
            return redirect()->route('dashboard.policy-requests.show', ['policyRequest' => $record->public_request_id])
                ->with('error', 'No issued provider event is available to reconcile yet.');
        }

        return redirect()->route('dashboard.policy-requests.show', ['policyRequest' => $record->public_request_id])
            ->with('success', 'Provider issuance was reconciled successfully.');
    }

    public function export(Request $request)
    {
        $query = $this->filteredRequestsQuery($request);
        $filename = 'policy-requests-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            fputcsv($output, [
                'Request ID',
                'Customer',
                'Agent ID',
                'Client Request ID',
                'Destination',
                'Plan',
                'Sum Insured',
                'Premium',
                'Status',
            ], ',', '"', '\\');

            foreach ($query->orderByDesc('created_at')->cursor() as $policy) {
                fputcsv($output, [
                    $this->spreadsheetSafe($policy->public_request_id),
                    $this->spreadsheetSafe($policy->customer_name),
                    $this->spreadsheetSafe($policy->agent_id),
                    $this->spreadsheetSafe($policy->client_request_id),
                    $this->spreadsheetSafe($policy->destination),
                    $this->spreadsheetSafe($policy->plan_code),
                    $policy->sum_insured,
                    $policy->premium_snapshot,
                    $this->spreadsheetSafe($policy->status),
                ], ',', '"', '\\');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(string $policyRequest)
    {
        $record = $this->findPolicyRequest($policyRequest);
        $record->load(['attempts', 'stateHistory', 'providerEvents']);

        return view('dashboard.policy-requests.show', ['request' => $record]);
    }

    private function findPolicyRequest(string $policyRequest): PolicyRequest
    {
        return PolicyRequest::where('public_request_id', $policyRequest)
            ->orWhere('id', $policyRequest)
            ->firstOrFail();
    }

    private function filteredRequestsQuery(Request $request): Builder
    {
        $query = PolicyRequest::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('agent_id', 'like', "%{$search}%")
                    ->orWhere('client_request_id', 'like', "%{$search}%")
                    ->orWhere('public_request_id', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
