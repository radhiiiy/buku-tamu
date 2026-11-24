@extends('layouts.admin')
@section('title', 'Bantuan')
@section('crumb', 'Pusat bantuan')
@section('nav', 'dashboard')
@section('content')
<div class="page-head"><div><h1>Pusat bantuan</h1><p>Panduan singkat mengelola kunjungan.</p></div></div>
<div class="card" style="max-width:760px">
    <ol class="steps">
        <li><span class="n">1</span><div><b>Tamu mengisi formulir</b><small>Tamu memindai QR Code ke halaman buku tamu ({{ url('/') }}). Anda juga bisa mencatat manual lewat <a href="{{ route('visits.create') }}">Tambah tamu</a>.</small></div></li>
        <li><span class="n">2</span><div><b>Konfirmasi kedatangan</b><small>Setelah penanggung jawab setuju, buka Detail dan tekan Konfirmasi. Gunakan Kirim pengingat bila belum direspons.</small></div></li>
        <li><span class="n">3</span><div><b>Check-out</b><small>Saat tamu pulang, tekan Check-out di Data kunjungan atau Detail. Durasi dihitung otomatis.</small></div></li>
        <li><span class="n">4</span><div><b>Laporan</b><small>Buka Laporan untuk rekap harian, ekspor Excel/CSV, atau kirim ke Google Sheets.</small></div></li>
    </ol>
</div>
@endsection
