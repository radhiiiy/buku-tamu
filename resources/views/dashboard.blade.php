@extends('layouts.admin')
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')
@section('nav', 'dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Nice to meet you {{ auth()->user()->name }}.</h1>
        <p>Pantau kedatangan dan sambut setiap tamu dengan lebih baik.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('reports.index') }}"><x-icon name="download" :size="16" /> Ekspor laporan</a>
        <a class="btn btn-primary" href="{{ route('visits.create') }}"><x-icon name="plus" :size="16" /> Tambah tamu</a>
    </div>
</div>
<div class="date-chip"><x-icon name="calendar" :size="15" /> {{ $today->translatedFormat('l, d F Y') }}</div>

@if ($overdue > 0)
    <div class="alert alert-warn">
        <b>{{ $overdue }} tamu belum check-out</b> lebih dari {{ config('visitor.checkout_reminder_hours') }} jam.
        <a href="{{ route('visits.index', ['status' => 'berkunjung']) }}">Tinjau &amp; check-out →</a>
    </div>
@endif

<div class="stats">
    <div class="card stat"><div class="l">Total tamu hari ini</div><div class="n">{{ $total }}</div>
        <div class="f {{ $diff > 0 ? 'up' : '' }}">@if ($diff > 0)↑ {{ $diff }} tamu dari kemarin @elseif ($diff < 0)↓ {{ abs($diff) }} tamu dari kemarin @else Sama seperti kemarin @endif</div>
        <span class="ic ic-blue"><x-icon name="users" /></span></div>
    <div class="card stat"><div class="l">Sedang berkunjung</div><div class="n">{{ $visiting }}</div><div class="f">Tamu berada di dalam</div><span class="ic ic-blue"><x-icon name="clock" /></span></div>
    <div class="card stat"><div class="l">Menunggu konfirmasi</div><div class="n">{{ $waiting }}</div><div class="f">Perlu ditindaklanjuti</div><span class="ic ic-amber"><x-icon name="bell" /></span></div>
    <div class="card stat"><div class="l">Kunjungan selesai</div><div class="n">{{ $done }}</div><div class="f">Sudah melakukan check-out</div><span class="ic ic-green"><x-icon name="check" /></span></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="row-between">
            <div><h2>Tren kunjungan</h2><p class="sub">Total {{ $trendTotal }} tamu dalam {{ $range }} hari terakhir</p></div>
            <form method="GET"><select name="range" class="input sel-mini" data-autosubmit aria-label="Rentang">
                @foreach ([7, 14, 30] as $r) <option value="{{ $r }}" @selected($range === $r)>{{ $r }} hari terakhir</option> @endforeach
            </select></form>
        </div>
        <div class="chart" role="img" aria-label="Grafik tren kunjungan">
            @foreach ([4, 3, 2, 1, 0] as $i)
                <div class="grid-line" style="top:{{ (4 - $i) * 25 }}%"><span>{{ $i * $axisStep }}</span></div>
            @endforeach
            <div class="bars">
                @foreach ($trend as $t)
                    <div class="bar {{ $loop->last ? 'today' : '' }}">
                        @if ($range <= 14)<em>{{ $t['count'] }}</em>@endif
                        <i style="height:{{ $axisMax ? round($t['count'] / $axisMax * 100, 1) : 0 }}%" title="{{ $t['date']->translatedFormat('j M') }}: {{ $t['count'] }}"></i>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="xlabels">
            @foreach ($trend as $t)
                <span class="{{ $loop->last ? 'today' : '' }}">{{ $range <= 7 ? $t['date']->translatedFormat('D, j') : ($loop->index % ceil($range / 7) === 0 || $loop->last ? $t['date']->translatedFormat('j M') : '') }}</span>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="row-between"><div><h2>Perlu konfirmasi</h2><p class="sub">Bantu tamu terhubung lebih cepat.</p></div><span class="tag tag-amber">{{ $pendingCount }}</span></div>
        <ul class="queue">
            @forelse ($pending as $v)
                <li><div><b>{{ $v->guest_name }}</b><small>{{ $v->host->name }} · {{ $v->arrived_at->diffForHumans() }}</small></div><a class="btn-chip" href="{{ route('visits.show', $v) }}">Detail</a></li>
            @empty
                <li><small>Tidak ada tamu yang menunggu. 🎉</small></li>
            @endforelse
        </ul>
        <a class="small b" href="{{ route('visits.index', ['status' => 'menunggu', 'date' => '']) }}">Lihat semua tamu menunggu →</a>
    </div>
</div>

<div class="card card-flush">
    <div class="toolbar" style="justify-content:space-between">
        <div><h2>Kunjungan terbaru</h2><p class="sub">Daftar tamu hari ini · {{ $total }} kunjungan</p></div>
        <form method="GET" class="toolbar" style="padding:0">
            <input type="hidden" name="range" value="{{ $range }}">
            <div class="search" style="min-width:260px"><x-icon name="search" :size="16" /><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Cari nama atau instansi..."></div>
            <select name="status" class="input" style="width:170px" data-autosubmit aria-label="Status">
                <option value="">Semua status</option>
                @foreach (\App\Models\Visit::STATUSES as $k => $l) <option value="{{ $k }}" @selected($statusFilter === $k)>{{ $l }}</option> @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr><th>Nama tamu</th><th>Bertemu dengan</th><th>Tujuan</th><th>Masuk</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse ($recent as $v)
                <tr>
                    <td><span class="name">{{ $v->guest_name }}</span><small>{{ $v->guest_company ?: '—' }}</small></td>
                    <td class="b">{{ $v->host->name }}</td>
                    <td class="muted">{{ $v->purpose }}</td>
                    <td class="time">{{ $v->arrived_at->format('H.i') }}</td>
                    <td><x-pill :status="$v->status" /></td>
                    <td><a class="small b" href="{{ route('visits.show', $v) }}">Detail →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="center muted" style="padding:34px">Belum ada kunjungan yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager-row"><span class="muted small">Menampilkan {{ $recent->count() }} terbaru</span><a class="small b" href="{{ route('visits.index') }}">Lihat semua kunjungan →</a></div>
</div>
@endsection
