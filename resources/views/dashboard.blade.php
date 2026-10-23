@extends('layouts.app')

@section('title', 'PolicyFlow Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">PolicyFlow dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Claims operations, premium validation, and insurer reconciliation.</p>
        </div>
        <a href="{{ route('dashboard.policy-requests.create') }}" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-500">
            Create policy request
        </a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @php $stats = [
            ['label' => 'Total requests', 'value' => $totals['total_requests'], 'tone' => 'bg-slate-900 text-white'],
            ['label' => 'Issued policies', 'value' => $totals['issued_policies'], 'tone' => 'bg-emerald-100 text-emerald-700'],
            ['label' => 'Pending confirmation', 'value' => $totals['pending_confirmation'], 'tone' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Failed requests', 'value' => $totals['failed_requests'], 'tone' => 'bg-red-100 text-red-700'],
            ['label' => 'Needs reconciliation', 'value' => $totals['needs_reconciliation'], 'tone' => 'bg-rose-100 text-rose-700'],
        ]; @endphp

        @foreach($stats as $stat)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">{{ $stat['label'] }}</span>
                    <span class="rounded-full px-2 py-1 text-xs {{ $stat['tone'] }}">Live</span>
                </div>
                <div class="mt-4 text-3xl font-semibold text-slate-900">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Recent policy requests</h2>
                <a href="{{ route('dashboard.policy-requests.index') }}" class="text-sm font-medium text-cyan-700">View all</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500">
                            <th class="py-3 pr-4 font-medium">Request</th>
                            <th class="py-3 pr-4 font-medium">Customer</th>
                            <th class="py-3 pr-4 font-medium">Policy</th>
                            <th class="py-3 pr-4 font-medium">Premium</th>
                            <th class="py-3 pr-4 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $request)
                            <tr class="border-b border-slate-100 text-slate-700">
                                <td class="py-3 pr-4">
                                    <a href="{{ route('dashboard.policy-requests.show', ['policyRequest' => $request->public_request_id]) }}" class="font-medium text-cyan-700 hover:underline">
                                        {{ $request->public_request_id }}
                                    </a>
                                </td>
                                <td class="py-3 pr-4">{{ $request->customer_name }}</td>
                                <td class="py-3 pr-4">{{ $request->plan_code }} / {{ $request->destination }}</td>
                                <td class="py-3 pr-4">₹{{ number_format((float) $request->premium_snapshot, 2) }}</td>
                                <td class="py-3 pr-4">
                                    <span class="status-badge status-{{ $request->status }}">{{ $request->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-sm text-slate-500">No policy requests yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Status distribution</h2>
                <div class="mt-4 space-y-3">
                    @foreach(['RECEIVED','VALIDATED','SUBMITTED','PENDING_CONFIRMATION','ISSUED','FAILED','NEEDS_RECONCILIATION'] as $status)
                        @php $count = $status_counts[$status] ?? 0; @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $status }}</span>
                                <span>{{ $count }}</span>
                            </div>
                            <div class="h-2.5 rounded-full bg-slate-100">
                                <div class="h-2.5 rounded-full bg-cyan-600" style="width: {{ $totals['total_requests'] ? ($count / max($totals['total_requests'], 1)) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Recent activity</h2>
                <ul class="mt-4 space-y-3 text-sm text-slate-600">
                    @foreach ($requests->take(4) as $recent)
                        <li class="flex items-start gap-3 rounded-lg bg-slate-50 p-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <div>
                                <p class="font-medium text-slate-800">{{ $recent->customer_name }}</p>
                                <p>{{ $recent->status }} · {{ $recent->plan_code }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
