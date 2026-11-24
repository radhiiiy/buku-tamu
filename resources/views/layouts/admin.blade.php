<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="poll-url" content="{{ route('notifications.poll') }}">
    <title>@yield('title', 'Dasbor') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php
    $nav = trim($__env->yieldContent('nav', 'dashboard'));
    $menu = [
        'dashboard' => ['Dashboard', 'grid', route('dashboard')],
        'visits' => ['Data kunjungan', 'users', route('visits.index')],
        'reports' => ['Laporan', 'file', route('reports.index')],
        'settings' => ['Pengaturan', 'settings', route('settings.show', 'profil')],
    ];
@endphp
<div class="app">
    <aside class="sidebar">
        <div class="brand">Dasbord</div>
        <div class="ws-label">WORKSPACE RESEPSIONIS</div>
        <div class="ws"><b>{{ $office['name'] }}</b><span>{{ $office['location'] }}</span></div>
        <div class="menu-label">MENU UTAMA</div>
        <nav class="nav">
            @foreach ($menu as $key => [$label, $icon, $href])
                <a href="{{ $href }}" class="{{ $nav === $key ? 'on' : '' }}"><x-icon :name="$icon" /> <span>{{ $label }}</span></a>
            @endforeach
        </nav>
        <div class="side-bottom">
            <div class="help-card">
                <b>Ada yang bisa dibantu?</b>
                <p>Panduan singkat untuk kelola kunjungan Anda.</p>
                <a href="{{ route('help') }}">Buka pusat bantuan ↗</a>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="logout" type="submit"><x-icon name="logout" /> <span>Keluar akun</span></button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="crumb">Workspace / <b>@yield('crumb', 'Dashboard')</b></div>
            <div class="top-right">
                <span class="live">Sistem aktif</span>
                <a id="bell" href="{{ route('notifications.index') }}" class="bell {{ $unread > 0 ? 'has' : '' }}" aria-label="Notifikasi"><x-icon name="bell" /><span class="dot"></span></a>
                <div class="profile">
                    <button id="profile-btn" type="button" aria-haspopup="true">
                        <span class="avatar"><x-icon name="user" /></span>
                        <div><b>{{ $me->name }}</b><small>{{ $me->roleLabel() }}</small></div>
                        <x-icon name="chevron-down" :size="14" />
                    </button>
                    <div id="profile-menu" class="menu-pop">
                        <a href="{{ route('settings.show', 'profil') }}">Pengaturan</a>
                        <a href="{{ route('notifications.index') }}">Notifikasi</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar akun</button></form>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            @if (session('success')) <div class="alert alert-ok" id="flash-toast" role="status">{{ session('success') }}</div> @endif
            @if (session('error')) <div class="alert alert-err" role="alert">{{ session('error') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
<div id="toast" class="toast" role="status"></div>
@stack('modals')
<script src="{{ asset('js/admin.js') }}" defer></script>
</body>
</html>
