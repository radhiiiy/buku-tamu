@extends('layouts.admin')
@section('title', 'Notifikasi')
@section('crumb', 'Notifikasi')
@section('nav', 'dashboard')
@section('content')
<div class="page-head">
    <div><h1>Pusat notifikasi</h1><p>Semua pembaruan tamu dan aktivitas resepsionis.</p></div>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn" type="submit">Tandai semua dibaca</button></form>
</div>
<div class="grid-main" style="grid-template-columns:1fr 330px">
    <div class="card card-flush" style="padding-bottom:20px">
        <nav class="tabs">
            <a class="{{ $tab === 'all' ? 'on' : '' }}" href="{{ route('notifications.index') }}">Semua</a>
            <a class="{{ $tab === 'unread' ? 'on' : '' }}" href="{{ route('notifications.index', ['tab' => 'unread']) }}">Belum dibaca ({{ $unread }})</a>
        </nav>
        <div style="padding:0 18px">
        @forelse ($groups as $day => $items)
            @php $d = \Illuminate\Support\Carbon::parse($day); @endphp
            <div class="n-day">{{ $d->isToday() ? 'HARI INI' : ($d->isYesterday() ? 'KEMARIN' : mb_strtoupper($d->translatedFormat('l'))) }} · {{ mb_strtoupper($d->translatedFormat('d F Y')) }}</div>
            @foreach ($items as $n)
                @php $cls = $n->type === 'visit_new' ? 'n-alert' : ($n->type === 'visit_rejected' ? 'n-bad' : 'n-ok'); @endphp
                <div class="n-item {{ $n->read_at ? '' : 'unread' }}">
                    <span class="n-ic {{ $cls }}">@if ($n->type === 'visit_new')!@elseif ($n->type === 'visit_rejected')×@else<x-icon name="check" :size="15" />@endif</span>
                    <div>
                        <b>{{ $n->title }}</b>
                        <p>{{ $n->body }}</p>
                        @if ($n->meta)<p class="xs">{{ $n->meta }}</p>@endif
                        <a href="{{ route('notifications.open', $n) }}">{{ $n->type === 'visit_new' ? 'Lihat kunjungan' : 'Lihat detail' }} →</a>
                    </div>
                    <span class="tm">{{ $n->created_at->format('H.i') }}</span>
                </div>
            @endforeach
        @empty
            <p class="center muted" style="padding:50px 0">{{ $tab === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}</p>
        @endforelse
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <h2>Preferensi notifikasi</h2><p class="sub" style="margin-bottom:24px">Atur saluran pemberitahuan Anda.</p>
            @foreach ([['notify_dashboard', 'Notifikasi dashboard', 'Pembaruan tamu secara langsung.'], ['notify_email', 'Notifikasi email', 'Kirim ringkasan ke email akun.'], ['notify_sound', 'Suara notifikasi', 'Bunyikan saat tamu baru datang.']] as [$key, $t, $d])
                <div class="pref"><div><b>{{ $t }}</b><small>{{ $d }}</small></div>
                    <label class="toggle"><input type="checkbox" data-pref="{{ $key }}" data-url="{{ route('notifications.prefs') }}" @checked($prefs[$key] ?? \App\Models\User::DEFAULT_PREFS[$key]) aria-label="{{ $t }}"><span></span></label></div>
            @endforeach
        </div>
        <div class="info-box"><b>Tetap terhubung dengan tamu</b><p>Notifikasi ke penanggung jawab mengikuti pengaturan organisasi. Hubungi admin untuk perubahan saluran pengiriman.</p></div>
    </div>
</div>
@endsection
