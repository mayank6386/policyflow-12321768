@extends('layouts.app')

@section('title', 'Create policy request')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-3xl font-semibold text-slate-900">Create policy request</h1>
        <p class="mt-1 text-sm text-slate-500">Capture underwriting details and validate the premium before submission.</p>
    </div>

    <form method="POST" action="{{ route('dashboard.policy-requests.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Client request ID</label>
                <input name="client_request_id" value="{{ old('client_request_id', $clientRequestId) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('client_request_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Agent ID</label>
                <input name="agent_id" value="{{ old('agent_id', 'AGENT-17') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('agent_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Customer name</label>
                <input name="customer_name" value="{{ old('customer_name', 'Sample Customer') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('customer_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Date of birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', '1998-04-10') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Destination</label>
                <select name="destination" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                    <option value="DOMESTIC" {{ old('destination') === 'DOMESTIC' ? 'selected' : '' }}>DOMESTIC</option>
                    <option value="INTERNATIONAL" {{ old('destination', 'INTERNATIONAL') === 'INTERNATIONAL' ? 'selected' : '' }}>INTERNATIONAL</option>
                </select>
                @error('destination') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Trip start</label>
                <input type="date" name="trip_start" value="{{ old('trip_start', '2026-11-10') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('trip_start') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Trip end</label>
                <input type="date" name="trip_end" value="{{ old('trip_end', '2026-11-14') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                @error('trip_end') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Plan code</label>
                <select name="plan_code" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                    @foreach(['BASIC','PLUS','PREMIUM'] as $plan)
                        <option value="{{ $plan }}" {{ old('plan_code', 'PLUS') === $plan ? 'selected' : '' }}>{{ $plan }}</option>
                    @endforeach
                </select>
                @error('plan_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sum insured</label>
                <select name="sum_insured" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 focus:border-cyan-500" required>
                    @foreach([100000,300000,500000] as $coverage)
                        <option value="{{ $coverage }}" {{ old('sum_insured', 300000) == $coverage ? 'selected' : '' }}>₹{{ number_format($coverage) }}</option>
                    @endforeach
                </select>
                @error('sum_insured') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($errors->any())
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('dashboard.policy-requests.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700">Cancel</a>
            <button class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-500">Create request</button>
        </div>
    </form>
</div>
@endsection
