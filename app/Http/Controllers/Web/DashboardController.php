<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        if (! Schema::hasTable('policy_requests')) {
            return view('dashboard', [
                'totals' => [
                    'total_requests' => 0,
                    'issued_policies' => 0,
                    'pending_confirmation' => 0,
                    'failed_requests' => 0,
                    'needs_reconciliation' => 0,
                ],
                'requests' => collect(),
                'status_counts' => [],
            ]);
        }

        $totals = PolicyRequest::selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'ISSUED' THEN 1 ELSE 0 END) as issued")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING_CONFIRMATION' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'NEEDS_RECONCILIATION' THEN 1 ELSE 0 END) as needs_reconciliation")
            ->first();

        $requests = PolicyRequest::latest()->limit(10)->get();

        return view('dashboard', [
            'totals' => [
                'total_requests' => (int) ($totals->total ?? 0),
                'issued_policies' => (int) ($totals->issued ?? 0),
                'pending_confirmation' => (int) ($totals->pending ?? 0),
                'failed_requests' => (int) ($totals->failed ?? 0),
                'needs_reconciliation' => (int) ($totals->needs_reconciliation ?? 0),
            ],
            'requests' => $requests,
            'status_counts' => PolicyRequest::select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
        ]);
    }
}
