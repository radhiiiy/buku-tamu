<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bukti kunjungan #{{ $visit->code }}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
<div class="print-page">
    <div class="row-between"><div class="p-brand sapa">sapa.</div><span class="muted small">{{ $office['name'] }}<br>{{ $office['location'] }}</span></div>
    <div class="xs muted b" style="margin-top:26px">BUKTI KUNJUNGAN</div>
    <div class="receipt" style="border:0;padding:0;margin:4px 0 0"><div class="code">#{{ $visit->code }}</div><x-pill :status="$visit->status" /></div>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0 8px">
    <div class="sum-row"><span>Nama tamu</span><b>{{ $visit->guest_name }}</b></div>
    <div class="sum-row"><span>Instansi</span><b>{{ $visit->guest_company ?: '—' }}</b></div>
    <div class="sum-row"><span>Bertemu dengan</span><b>{{ $visit->host->name }}</b></div>
    <div class="sum-row"><span>Tujuan</span><b>{{ $visit->purpose }}</b></div>
    <div class="sum-row"><span>Waktu masuk</span><b>{{ $visit->arrived_at->translatedFormat('d M Y') }} · {{ $visit->arrived_at->format('H.i') }} WIB</b></div>
    @if ($visit->checked_out_at)<div class="sum-row"><span>Waktu keluar</span><b>{{ $visit->checked_out_at->format('H.i') }} WIB · {{ $visit->duration_minutes }} menit</b></div>@endif
    <p class="muted xs center" style="margin-top:22px">Simpan bukti ini untuk ditunjukkan ke petugas.</p>
    <div class="actions no-print" style="justify-content:center;margin-top:20px"><button class="btn btn-primary" type="button" data-print>Cetak</button><button class="btn" type="button" onclick="window.close()">Tutup</button></div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));document.querySelector('[data-print]').addEventListener('click', () => window.print());</script>
</body></html>
