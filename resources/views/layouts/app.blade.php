<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Schedule') · Sewing Scheduler</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="topbar">
    <strong class="brand">Sewing Scheduler</strong>
    <nav>
        <a href="{{ route('schedules.index') }}" @class(['active' => request()->routeIs('schedules.*')])>Schedule</a>
        <a href="{{ route('orders.index') }}" @class(['active' => request()->routeIs('orders.*')])>Master Order</a>
        <a href="{{ route('lines.index') }}" @class(['active' => request()->routeIs('lines.*')])>Master Line</a>
    </nav>
    <form method="POST" action="{{ route('logout') }}" style="margin-left:auto;display:flex;align-items:center;gap:8px">
        @csrf
        <small>{{ auth()->user()->name }}</small>
        <button type="submit" class="btn sm">Keluar</button>
    </form>
</header>

<main class="container">
    @yield('content')
</main>

<div id="toasts" aria-live="polite"></div>

<dialog id="confirm-dialog" class="dialog narrow">
    <form method="dialog">
        <p data-confirm-msg></p>
        <div class="dialog-actions">
            <button class="btn" value="cancel">Batal</button>
            <button class="btn danger" value="ok">Ya, lanjutkan</button>
        </div>
    </form>
</dialog>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
