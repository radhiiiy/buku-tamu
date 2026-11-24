@extends('layouts.admin')
@section('title', 'Google Sheets')
@section('crumb', 'Google Sheets')
@section('nav', 'reports')
@section('content')
@php $me = auth()->user(); @endphp
<a class="back" href="{{ route('reports.index', $q) }}"><x-icon name="arrow-left" :size="14" /> Laporan kunjungan</a>
<div class="page-head"><div><h1>Kirim ke Google Sheets</h1><p>Simpan laporan ke spreadsheet tanpa mengunduh berkas.</p></div></div>
@if ($errors->any()) <div class="alert alert-err">{{ $errors->first() }}</div> @endif
@unless ($configured) <div class="alert alert-warn">Integrasi Google belum dikonfigurasi. Isi <code>GOOGLE_CLIENT_ID</code> dan <code>GOOGLE_CLIENT_SECRET</code> di <code>.env</code> (OAuth client dengan redirect <code>{{ config('services.google.redirect') }}</code>).</div> @endunless

<form method="POST" action="{{ route('reports.sheets.store') }}">
    @csrf
    @foreach ($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <div class="grid-main" style="margin-bottom:22px">
        <div class="stack">
            <div class="card">
                <h2 style="margin-bottom:18px">Akun &amp; tujuan ekspor</h2>
                <div class="callout" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <div><span class="muted xs">Akun Google terhubung</span><br><b>{{ $me->google_email ?: ($me->googleConnected() ? 'Terhubung' : 'Belum terhubung') }}</b></div>
                    @if ($me->googleConnected())
                        <div class="cell-act"><a class="small b" href="{{ route('google.connect', ['ganti' => 1]) }}">Ganti akun</a>
                            <button class="link-btn" type="submit" form="google-disconnect" style="color:var(--red)">Putuskan</button></div>
                    @else
                        <a class="btn btn-sm btn-primary" href="{{ route('google.connect') }}" @if(! $configured) aria-disabled="true" style="opacity:.5;pointer-events:none" @endif>Hubungkan akun Google</a>
                    @endif
                </div>

                <label style="margin-top:6px">Tujuan spreadsheet</label>
                <div class="radio-cards">
                    <label class="rc"><b><input type="radio" name="mode" value="new" @checked(old('mode', 'new') === 'new')> Buat spreadsheet baru</b></label>
                    <label class="rc"><b><input type="radio" name="mode" value="existing" @checked(old('mode') === 'existing')> Gunakan yang sudah ada</b></label>
                </div>
                <div id="new-box" class="field"><label for="title">Nama spreadsheet</label><input id="title" name="title" class="input" value="{{ old('title', $title) }}" maxlength="120"></div>
                <div id="existing-box" class="field"><label for="existing">Tautan atau ID spreadsheet</label><input id="existing" name="existing" class="input" value="{{ old('existing') }}" placeholder="https://docs.google.com/spreadsheets/d/…"><p class="hint">Tab baru akan ditambahkan; tab yang sudah ada tidak diubah.</p></div>
                <div class="form-grid">
                    <div class="field"><label for="tab">Nama tab data</label><input id="tab" name="tab" class="input" value="{{ old('tab', 'Data kunjungan') }}" maxlength="60"></div>
                    <div class="field"><label for="loc">Lokasi penyimpanan</label><select id="loc" class="input" disabled><option>Drive Saya</option></select></div>
                </div>
                <label class="check"><input type="checkbox" name="summary" value="1" @checked(old('summary', true))> Tambahkan tab Ringkasan harian</label>
                <p class="hint" style="margin-top:12px">Data baru dibuat terpisah; spreadsheet lain tidak diubah.</p>
            </div>
            <div class="card">
                <h2 style="margin-bottom:16px">Opsi data</h2>
                <label class="check" style="margin-bottom:12px"><input type="checkbox" name="header" value="1" @checked(old('header', true))> Gunakan baris pertama sebagai header</label>
                <label class="check {{ $phoneAllowed ? '' : 'off' }}" @if(! $phoneAllowed) style="color:#a3aec6" @endif><input type="checkbox" name="with_phone" value="1" @checked(old('with_phone')) @disabled(! $phoneAllowed)> Sertakan nomor WhatsApp (data pribadi)</label>
                <p class="hint" style="margin-top:14px">Format tanggal: YYYY-MM-DD · Zona waktu: WIB (Asia/Jakarta)</p>
            </div>
        </div>
        <div class="stack">
            <div class="card sum-card">
                <h2>Ringkasan pengiriman</h2>
                <dl>
                    <div class="sum-row"><span>Periode</span><b>{{ $f['from']->translatedFormat('j') }}–{{ $f['to']->translatedFormat('j M Y') }}</b></div>
                    <div class="sum-row"><span>Jumlah data</span><b>{{ $total }} baris</b></div>
                    <div class="sum-row"><span>Kolom data</span><b>{{ count($cols) }} kolom terpilih</b></div>
                    <div class="sum-row"><span>Akses awal</span><b>Hanya akun Anda</b></div>
                </dl>
                <div class="info-box green" style="margin:16px 0"><b>Ekspor satu kali</b><p>Bukan sinkronisasi otomatis.</p></div>
                <button class="btn btn-green btn-block" type="submit" @disabled(! $configured || ! $me->googleConnected())><x-icon name="arrow-up-right" :size="15" /> Buat &amp; kirim ke Sheets</button>
                <p class="center muted xs" style="margin-top:12px">Pengaturan berbagi tetap mengikuti Google Drive.</p>
            </div>
            <div class="info-box"><b>Anda mengontrol aksesnya</b><p>Laporan tidak dibagikan secara publik. Atur penerima dan izin akses langsung dari Google Sheets setelah ekspor.</p></div>
        </div>
    </div>
</form>
<form id="google-disconnect" method="POST" action="{{ route('google.disconnect') }}">@csrf</form>
@endsection
@push('modals')
@if ($done)
<div class="overlay open" role="dialog" aria-modal="true" aria-labelledby="sd-title">
    <div class="modal">
        <a class="x" href="{{ route('reports.sheets', $q) }}" aria-label="Tutup"><x-icon name="x" /></a>
        <div class="badge ic-green"><x-icon name="check" :size="22" /></div>
        <h2 id="sd-title">Laporan berhasil dikirim</h2>
        <p class="lead">Spreadsheet telah dibuat atau diperbarui di Drive Anda. Hanya akun Anda yang memiliki akses awal.</p>
        <div class="box"><b>{{ $done['title'] }}</b>
            <p class="muted small" style="margin-top:10px">{{ $done['rows'] }} baris kunjungan · {{ $done['cols'] }} kolom</p>
            <p class="muted xs" style="margin-top:8px">Tab: {{ $done['tabs'] }}</p>
            <p class="muted xs" style="margin-top:8px">Periode {{ $done['period'] }} · {{ $done['phone'] ? 'termasuk nomor WhatsApp' : 'tanpa nomor WhatsApp' }}</p></div>
        <p class="muted small">Atur izin berbagi langsung di Google Sheets. Pengiriman ini tidak mengaktifkan sinkronisasi otomatis.</p>
        <div class="foot"><a class="btn" href="{{ route('reports.index', $q) }}">Kembali ke laporan</a><a class="btn btn-primary" href="{{ $done['url'] }}" target="_blank" rel="noopener"><x-icon name="arrow-up-right" :size="15" /> Buka Google Sheets</a></div>
    </div>
</div>
@endif
@endpush
