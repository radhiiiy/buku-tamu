@extends('layouts.admin')
@section('title', 'Data kunjungan')
@section('crumb', 'Data kunjungan')
@section('nav', 'visits')
@php
    $tabs = ['all' => ['Semua', $allCount], 'menunggu' => ['Menunggu', $counts['menunggu'] ?? 0], 'berkunjung' => ['Berkunjung', $counts['berkunjung'] ?? 0], 'selesai' => ['Selesai', $counts['selesai'] ?? 0], 'ditolak' => ['Ditolak', $counts['ditolak'] ?? 0]];
    $exportQuery = $date ? ['from' => $date->format('Y-m-d'), 'to' => $date->format('Y-m-d')] : [];
@endphp
@section('content')
<div class="page-head">
    <div><h1>Data kunjungan</h1><p>Kelola kedatangan, konfirmasi, dan kepulangan tamu dalam satu tempat.</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('reports.excel', $exportQuery) }}"><x-icon name="download" :size="15" /> Ekspor data</a>
        <a class="btn btn-primary" href="{{ route('visits.create') }}"><x-icon name="plus" :size="15" /> Tambah tamu</a>
    </div>
</div>

<div class="stats">
    <div class="card stat simple"><div class="l">Semua kunjungan</div><div class="n c-blue">{{ $allCount }}</div></div>
    <div class="card stat simple"><div class="l">Menunggu</div><div class="n c-amber">{{ $counts['menunggu'] ?? 0 }}</div></div>
    <div class="card stat simple"><div class="l">Berkunjung</div><div class="n c-blue">{{ $counts['berkunjung'] ?? 0 }}</div></div>
    <div class="card stat simple"><div class="l">Selesai</div><div class="n c-green">{{ $counts['selesai'] ?? 0 }}</div></div>
</div>

<div class="card card-flush">
    <nav class="tabs" aria-label="Status">
        @foreach ($tabs as $key => [$label, $n])
            @continue($key === 'ditolak' && $n === 0 && $tab !== 'ditolak')
            <a class="{{ $tab === $key ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['status' => $key === 'all' ? null : $key, 'page' => null]) }}">{{ $label }} ({{ $n }})</a>
        @endforeach
    </nav>

    <form method="GET" class="filters">
        @if ($tab !== 'all') <input type="hidden" name="status" value="{{ $tab }}"> @endif
        <input type="hidden" name="sort" value="{{ $sort }}">
        <div class="search grow"><x-icon name="search" :size="16" /><input class="input" type="search" name="q" value="{{ $term }}" placeholder="Cari nama, instansi, atau ID kunjungan"></div>
        <input class="input" type="date" name="date" value="{{ $date?->format('Y-m-d') }}" aria-label="Tanggal" title="Kosongkan untuk semua tanggal">
        <select class="input" name="host" aria-label="Penanggung jawab">
            <option value="">Semua penanggung jawab</option>
            @foreach ($hosts as $h) <option value="{{ $h->id }}" @selected($hostId === $h->id)>{{ $h->name }}</option> @endforeach
        </select>
        <button class="btn btn-primary" type="submit">Terapkan</button>
    </form>

    <div class="meta-row">
        <span>{{ $list->total() }} kunjungan ditemukan{{ $date ? '' : ' · semua tanggal' }}</span>
        <form method="GET">
            @foreach (request()->except(['sort', 'page']) as $k => $v) @if (is_scalar($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif @endforeach
            Urutkan: <select name="sort" data-autosubmit><option value="terbaru" @selected($sort === 'terbaru')>Terbaru</option><option value="terlama" @selected($sort === 'terlama')>Terlama</option></select>
        </form>
    </div>

    <div id="bulk-bar" class="bulk">
        <b><span id="bulk-count">0</span> dipilih</b>
        <button type="button" class="btn btn-sm" data-bulk="confirm">Konfirmasi terpilih</button>
        <button type="button" class="btn btn-sm" data-bulk="checkout">Check-out terpilih</button>
    </div>
    <form id="bulk-form" method="POST" action="{{ route('visits.bulk') }}">@csrf<input type="hidden" name="action" value=""></form>

    <div class="table-wrap">
        <table class="t">
            <thead><tr><th style="width:36px"><input type="checkbox" id="select-all" aria-label="Pilih semua"></th><th>Tamu / instansi</th><th>Bertemu dengan</th><th>Tujuan</th><th>Masuk</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse ($list as $v)
                <tr>
                    <td><input type="checkbox" class="row-check" value="{{ $v->id }}" aria-label="Pilih {{ $v->guest_name }}"></td>
                    <td><span class="name">{{ $v->guest_name }}</span><small>{{ $v->guest_company ?: '—' }}</small></td>
                    <td class="b">{{ $v->host->name }}</td>
                    <td class="muted">{{ $v->purpose }}</td>
                    <td class="time">{{ $v->arrived_at->format('H.i') }}@if (! $date)<small>{{ $v->arrived_at->translatedFormat('d M') }}</small>@endif</td>
                    <td><x-pill :status="$v->status" /></td>
                    <td><div class="cell-act">
                        <a class="small b" href="{{ route('visits.show', $v) }}">Detail →</a>
                        @if ($v->status === 'berkunjung')
                            <button type="button" class="btn btn-sm js-checkout" data-preview="{{ route('visits.checkout.preview', $v) }}">Check-out</button>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted" style="padding:40px">Tidak ada kunjungan yang cocok dengan filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <x-pager :paginator="$list" />
</div>
@endsection
@push('modals') @include('partials.checkout-modal') @endpush
