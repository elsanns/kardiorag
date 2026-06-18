<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KardioRAG')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="wrap">
            <a class="brand" href="{{ route('ask.index') }}">KardioRAG</a>
            <nav>
                <a href="{{ route('ask.index') }}" @class(['active' => request()->routeIs('ask.index')])>Ask</a>
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            </nav>
            <span class="tag">on-prem RAG · openFDA</span>
        </div>
    </header>

    <main class="wrap">
        @yield('content')
    </main>

    <footer class="wrap foot">
        Cardiology drug-information assistant · data: openFDA (public) · answers are grounded in cited sources and not medical advice.
    </footer>
</body>
</html>
