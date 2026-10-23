@extends('layouts.app')

@section('title', 'Policy details')

@section('content')
<div class="space-y-6">
    @if (session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-slate-400">Policy request</p>
            <h1 class="text-3xl font-semibold text-slate-900">{{ $request->public_request_id }}</h1>
        </div>
        <span class="status-badge status-{{ $request->status }}">{{ $request->status }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Policy summary</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm text-slate-600">
                    <div><dt class="text-slate-400">Customer</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->customer_name }}</dd></div>
                    <div><dt class="text-slate-400">Agent ID</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->agent_id }}</dd></div>
                    <div><dt class="text-slate-400">Client ref</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->client_request_id }}</dd></div>
                    <div><dt class="text-slate-400">Travel window</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->trip_start->format('d M Y') }} → {{ $request->trip_end->format('d M Y') }}</dd></div>
                    <div><dt class="text-slate-400">Destination</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->destination }}</dd></div>
                    <div><dt class="text-slate-400">Plan</dt><dd class="mt-1 font-medium text-slate-900">{{ $request->plan_code }}</dd></div>
                    <div><dt class="text-slate-400">Sum insured</dt><dd class="mt-1 font-medium text-slate-900">₹{{ number_format((float) $request->sum_insured, 0) }}</dd></div>
                    <div><dt class="text-slate-400">Premium snapshot</dt><dd class="mt-1 font-medium text-slate-900">₹{{ number_format((float) $request->premium_snapshot, 2) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">State history</h2>
                <div class="mt-4 space-y-3">
                    @foreach($request->stateHistory()->orderBy('id')->get() as $event)
                        <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-cyan-500"></span>
                            <div>
                                <p class="font-medium text-slate-800">{{ $event->from_state }} → {{ $event->to_state }}</p>
                                <p class="text-xs text-slate-500">{{ $event->source }} · {{ $event->reason }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Provider details</h2>
                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                    <div><dt class="text-slate-400">Reference</dt><dd class="font-medium text-slate-900">{{ $request->provider_reference ?? 'Not yet assigned' }}</dd></div>
                    <div><dt class="text-slate-400">Policy number</dt><dd class="font-medium text-slate-900">{{ $request->provider_policy_number ?? 'Pending' }}</dd></div>
                    <div><dt class="text-slate-400">Last response</dt><dd class="font-medium text-slate-900">{{ is_array($request->last_provider_response) ? json_encode($request->last_provider_response) : ($request->last_provider_response ?? 'None') }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Operational controls</h2>
                <div class="mt-4 space-y-3">
                    @if (in_array($request->status, ['VALIDATED', 'FAILED', 'NEEDS_RECONCILIATION'], true))
                        <form method="POST" action="{{ route('dashboard.policy-requests.submit', ['policyRequest' => $request->public_request_id]) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500">Submit for issuance</button>
                        </form>
                    @endif
                    @if (in_array($request->status, ['PENDING_CONFIRMATION', 'NEEDS_RECONCILIATION'], true))
                        <form method="POST" action="{{ route('dashboard.policy-requests.reconcile', ['policyRequest' => $request->public_request_id]) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">Reconcile provider status</button>
                        </form>
                    @endif
                    <a href="{{ route('dashboard.policy-requests.index') }}" class="block w-full rounded-xl border border-slate-200 px-4 py-2 text-center text-sm font-medium text-slate-700">Back to list</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
