<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PolicyFlow')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="min-h-screen">
        <aside class="fixed inset-y-0 left-0 hidden w-72 border-r border-slate-200 bg-[#0f172a] p-5 text-slate-100 lg:block">
            <div class="flex items-center gap-3 border-b border-slate-700 pb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/20 text-lg font-bold text-emerald-300">P</div>
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Operations</p>
                    <h1 class="text-xl font-semibold">PolicyFlow</h1>
                </div>
            </div>

            <nav class="mt-8 space-y-2 text-sm">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl bg-slate-800 px-3 py-2 text-white">
                    Overview
                </a>
                <a href="{{ route('dashboard.policy-requests.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white">
                    Policy requests
                </a>
                <a href="{{ route('dashboard.policy-requests.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white">
                    Create request
                </a>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="border-b border-slate-200 bg-white/80 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Insurance operations</p>
                        <h2 class="text-xl font-semibold text-slate-900">Control Center</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard.policy-requests.export', request()->query()) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">Export CSV</a>
                        <div class="flex items-center gap-2 rounded-full bg-slate-100 px-2 py-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <span class="text-sm text-slate-700">Ops live</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
