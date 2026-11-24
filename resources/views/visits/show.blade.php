@extends('layouts.admin')
@section('title', 'Detail kunjungan')
@section('crumb', 'Detail kunjungan')
@section('nav', 'visits')
@php
    $h = $visit->host;
    $notified = $visit->events->firstWhere('title', 'Penanggung jawab diberi tahu');
@endphp
@section('content')
<a class="back" href="{{ route('visits.index') }}"><x-icon name="arrow-left" :size="14" /> Data kunjungan</a>
<div class="page-head">
    <div><h1>Detail kunjungan</h1><p>#{{ $visit->code }} · {{ $visit->arrived_at->translatedFormat('l, d F Y') }}</p></div>
    <a class="btn" href="{{ route('visits.print', $visit) }}" target="_blank" rel="noopener"><x-icon name="printer" :size="15" /> Cetak bukti</a>
</div>

<div class="grid-main">
    <div class="stack">
        <div class="card card-flush">
            <div class="info-head"><h2>Informasi tamu</h2><x-pill :status="$visit->status" /></div>
            <dl class="kvs info-body">
                <div class="kv"><dt>Nama lengkap</dt><dd>{{ $visit->guest_name }}</dd></div>
                <div class="kv"><dt>Instansi / perusahaan</dt><dd>{{ $visit->guest_company ?: '—' }}</dd></div>
                <div class="kv"><dt>Nomor WhatsApp</dt><dd>{{ $visit->guest_phone }}</dd></div>
                @if ($visit->guest_email)<div class="kv"><dt>Email</dt><dd>{{ $visit->guest_email }}</dd></div>@endif
                <div class="kv"><dt>Tujuan kunjungan</dt><dd>{{ $visit->purpose }}</dd></div>
                <div class="kv"><dt>Bertemu dengan</dt><dd>{{ $h->label }}</dd></div>
                <div class="kv"><dt>Catatan</dt><dd>{{ $visit->notes ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="card">
            <h2>Aktivitas kunjungan</h2>
            <div class="timeline">
                @foreach ($visit->events as $e)
                    <div class="tl"><span class="dot"></span><span class="tm">{{ $e->created_at->format('H.i') }}</span><div><b>{{ $e->title }}</b><small>{{ $e->description }}</small></div></div>
                @endforeach
                @if ($visit->status === 'menunggu')
                    <div class="tl"><span class="dot now"></span><span class="tm">Saat ini</span><div><b>Menunggu konfirmasi</b><small>Tamu diarahkan menunggu di area resepsionis.</small></div></div>
                @elseif ($visit->status === 'berkunjung')
                    <div class="tl"><span class="dot now"></span><span class="tm">Saat ini</span><div><b>Sedang berkunjung</b><small>Check-out saat tamu meninggalkan kantor.</small></div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Tindak lanjut kunjungan</h2>
            <p class="sub">
                @if ($visit->status === 'menunggu') Konfirmasi setelah penanggung jawab menyetujui kedatangan tamu.
                @elseif ($visit->status === 'berkunjung') Tamu sedang berada di kantor.
                @elseif ($visit->status === 'selesai') Kunjungan ini sudah selesai.
                @else Kunjungan ini ditolak. @endif
            </p>
            @if ($notified)
                <div class="notice"><b>Notifikasi terkirim ke {{ $h->name }}</b><small>{{ $notified->created_at->isToday() ? 'Hari ini' : $notified->created_at->translatedFormat('d M Y') }}, {{ $notified->created_at->format('H.i') }} WIB · {{ ($via = collect([$h->email ? 'email' : null, $h->phone && app(\App\Services\WhatsAppService::class)->enabled() ? 'WhatsApp' : null])->filter()->implode(' & ')) ? 'melalui '.$via : 'penanggung jawab belum punya email/WhatsApp' }}</small></div>
            @endif
            <div class="act-stack">
                @if ($visit->status === 'menunggu')
                    <form method="POST" action="{{ route('visits.confirm', $visit) }}">@csrf<button class="btn btn-primary btn-block" style="justify-content:flex-start"><x-icon name="check" :size="15" /> Konfirmasi kedatangan</button></form>
                    <form method="POST" action="{{ route('visits.remind', $visit) }}">@csrf<button class="btn btn-block" style="justify-content:flex-start">Kirim pengingat ke penanggung jawab</button></form>
                    <form method="POST" action="{{ route('visits.reject', $visit) }}" onsubmit="return confirm('Tolak kunjungan {{ e($visit->guest_name) }}?')" class="center">@csrf<button class="btn-danger-link">Tolak kunjungan</button></form>
                @elseif ($visit->status === 'berkunjung')
                    <button type="button" class="btn btn-primary btn-block js-checkout" data-preview="{{ route('visits.checkout.preview', $visit) }}">Check-out tamu</button>
                @endif
            </div>
        </div>

        <div class="card">
            <h2>Ringkasan waktu</h2>
            <div style="margin-top:14px">
                <div class="sum-row"><span>Waktu masuk</span><b>{{ $visit->arrived_at->format('H.i') }} WIB</b></div>
                <div class="sum-row"><span>Waktu keluar</span><b>{{ $visit->checked_out_at ? $visit->checked_out_at->format('H.i').' WIB' : 'Belum check-out' }}</b></div>
                <div class="sum-row"><span>Durasi kunjungan</span><b>
                    @if ($visit->duration_minutes !== null) {{ $visit->duration_minutes }} menit
                    @elseif ($visit->status === 'berkunjung') Berjalan {{ (int) $visit->arrived_at->diffInMinutes(now(), true) }} menit
                    @else Belum dimulai @endif</b></div>
                <div class="sum-row"><span>Dicatat melalui</span><b>{{ $visit->source === 'qr' ? 'Formulir QR Code' : 'Input manual resepsionis' }}</b></div>
            </div>
            @if ($visit->status === 'menunggu') <p class="muted small" style="margin-top:14px">Check-out tersedia setelah tamu dikonfirmasi.</p> @endif
        </div>
    </div>
</div>
@endsection
@push('modals') @include('partials.checkout-modal') @endpush
