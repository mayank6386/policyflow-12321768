@extends('layouts.app')

@section('title', 'Policy requests')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-slate-900">Policy requests</h1>
            <p class="text-sm text-slate-500">Search, filter, and triage underwriting requests.</p>
        </div>
        <a href="{{ route('dashboard.policy-requests.create') }}" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500">New policy request</a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" class="flex flex-col gap-3 md:flex-row md:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Customer, agent, request ID" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none ring-0 focus:border-cyan-500">
            </div>
            <div class="w-full md:w-56">
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select name="status" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 outline-none focus:border-cyan-500">
                    <option value="">All statuses</option>
                    @foreach(['RECEIVED','VALIDATED','SUBMITTED','PENDING_CONFIRMATION','ISSUED','FAILED','NEEDS_RECONCILIATION'] as $status)
                        <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white">Apply filters</button>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Request ID</th>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Agent</th>
                        <th class="px-4 py-3 font-medium">Destination</th>
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Premium</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr class="border-t border-slate-100 text-slate-700">
                            <td class="px-4 py-3">
                                <a href="{{ route('dashboard.policy-requests.show', ['policyRequest' => $request->public_request_id]) }}" class="font-medium text-cyan-700 hover:underline">{{ $request->public_request_id }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $request->customer_name }}</td>
                            <td class="px-4 py-3">{{ $request->agent_id }}</td>
                            <td class="px-4 py-3">{{ $request->destination }}</td>
                            <td class="px-4 py-3">{{ $request->plan_code }}</td>
                            <td class="px-4 py-3">₹{{ number_format((float) $request->premium_snapshot, 2) }}</td>
                            <td class="px-4 py-3"><span class="status-badge status-{{ $request->status }}">{{ $request->status }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No requests match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
