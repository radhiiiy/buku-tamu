@extends('layouts.admin')
@section('title', 'Ekspor Excel')
@section('crumb', 'Ekspor Excel')
@section('nav', 'reports')
@section('content')
<a class="back" href="{{ route('reports.index', $q) }}"><x-icon name="arrow-left" :size="14" /> Laporan kunjungan</a>
<div class="page-head"><div><h1>Ekspor ke Excel</h1><p>Sesuaikan format dan kolom sebelum mengunduh laporan.</p></div></div>
@if ($errors->any()) <div class="alert alert-err">{{ $errors->first() }}</div> @endif

<form id="export-form" method="POST" action="{{ route('reports.excel.store') }}" data-query="{{ http_build_query($q) }}" data-preview="{{ route('reports.preview') }}">
    @csrf
    @foreach ($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <div class="grid-main" style="grid-template-columns:1fr 330px;margin-bottom:22px">
        <div class="card">
            <h2 style="margin-bottom:18px">Format &amp; isi laporan</h2>
            <div class="radio-cards">
                <label class="rc"><b><input type="radio" name="format" value="xlsx" checked> Excel (.xlsx)</b><small>Dua tab: ringkasan dan data lengkap</small></label>
                <label class="rc"><b><input type="radio" name="format" value="csv"> CSV (.csv)</b><small>Data mentah, dipisahkan koma</small></label>
            </div>
            <div class="field"><label for="filename">Nama berkas</label><input id="filename" name="filename" class="input" value="{{ old('filename', $filename) }}" maxlength="120"></div>
            <div class="row-between" style="margin-bottom:12px"><label style="margin:0">Kolom yang disertakan</label><span id="col-count" class="small c-blue b"></span></div>
            <div class="cols">
                @foreach ($columns as $key => $label)
                    @php $ok = in_array($key, $allowed, true); @endphp
                    <label class="check {{ $ok ? '' : 'off' }}"><input type="checkbox" name="cols[]" value="{{ $key }}" @checked(in_array($key, $cols, true)) @disabled(! $ok)> {{ $label }}</label>
                @endforeach
            </div>
            @if (! $phoneAllowed)<p class="hint" style="margin-top:14px">Kolom Nomor WhatsApp terkunci: butuh izin akun dan pengaturan Privasi data yang aktif.</p>@endif
        </div>

        <div class="stack">
            <div class="card sum-card">
                <h2>Ringkasan ekspor</h2>
                <dl>
                    <div class="sum-row"><span>Periode</span><b>{{ $f['from']->translatedFormat('j') }}–{{ $f['to']->translatedFormat('j M Y') }}</b></div>
                    <div class="sum-row"><span>Status</span><b>{{ $f['status'] === 'all' ? 'Semua status' : \App\Models\Visit::STATUSES[$f['status']] }}</b></div>
                    <div class="sum-row"><span>Penanggung jawab</span><b>{{ $hostName }}</b></div>
                    <div class="sum-row"><span>Jumlah data</span><b>{{ $total }} baris · <span class="js-colsum">{{ count($cols) }} kolom</span></b></div>
                </dl>
                <div id="tabs-box" class="mini-tabs"><span>Tab 1: Ringkasan</span><span>Tab 2: Data kunjungan</span></div>
                <button id="btn-export" class="btn btn-primary btn-block" type="submit"><x-icon name="download" :size="15" /> Unduh <span class="js-ext">Excel (.xlsx)</span></button>
                <p class="center muted xs" style="margin-top:12px">{{ $total }} baris · termasuk header kolom</p>
            </div>
            <div class="info-box"><b>Bagikan hanya yang diperlukan</b><p>Nomor WhatsApp tidak disertakan. Aktifkan kolom tersebut hanya jika dibutuhkan dan Anda memiliki izin.</p></div>
        </div>
    </div>
    <div id="preview-slot">@include('reports.partials.preview')</div>
</form>
@endsection
@push('modals')
@if ($done)
<div class="overlay open" role="dialog" aria-modal="true" aria-labelledby="done-title" data-autodownload="{{ route('reports.download', $done) }}">
    <div class="modal">
        <a class="x" href="{{ route('reports.excel', $q) }}" aria-label="Tutup"><x-icon name="x" /></a>
        <div class="badge ic-green"><x-icon name="check" :size="22" /></div>
        <h2 id="done-title">{{ $done->kind === 'csv' ? 'CSV' : 'Excel' }} berhasil dibuat</h2>
        <p class="lead">Laporan telah disiapkan sesuai filter dan kolom yang Anda pilih.</p>
        <div class="box">
            <b>{{ $done->filename }}</b>
            <p class="muted small" style="margin-top:10px">{{ $done->rows_count }} baris kunjungan · {{ $done->columns_count }} kolom</p>
            <p class="muted xs" style="margin-top:8px">{{ $done->kind === 'xlsx' ? 'Tab: Ringkasan + Data kunjungan' : 'Satu berkas CSV (UTF-8)' }}</p>
            <p class="muted xs" style="margin-top:8px">Periode {{ $done->period_from->translatedFormat('j') }}–{{ $done->period_to->translatedFormat('j M Y') }} · {{ in_array('phone', $done->filters['cols'] ?? [], true) ? 'termasuk nomor WhatsApp' : 'tanpa nomor WhatsApp' }}</p>
        </div>
        <p class="muted small">Unduhan dimulai otomatis. Jika belum muncul, gunakan tombol Unduh ulang di bawah.</p>
        <div class="foot"><a class="btn" href="{{ route('reports.index', $q) }}">Kembali ke laporan</a><a class="btn btn-primary" href="{{ route('reports.download', $done) }}"><x-icon name="download" :size="15" /> Unduh ulang</a></div>
    </div>
</div>
@endif
@endpush
