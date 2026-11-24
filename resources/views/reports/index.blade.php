@extends('layouts.admin')
@section('title', 'Laporan')
@section('crumb', 'Laporan')
@section('nav', 'reports')
@section('content')
<div class="page-head">
    <div><h1>Laporan kunjungan</h1><p>Ubah data kunjungan menjadi rekap yang mudah dibagikan.</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('reports.sheets', $q) }}"><x-icon name="arrow-up-right" :size="15" /> Google Sheets</a>
        <a class="btn btn-primary" href="{{ route('reports.excel', $q) }}"><x-icon name="download" :size="15" /> Ekspor Excel</a>
    </div>
</div>

<form method="GET" class="card" style="display:grid;grid-template-columns:2fr 1.1fr 1.1fr auto;gap:16px;align-items:end;margin-bottom:22px">
    <div><label class="xs muted">Periode laporan</label>
        <div style="display:flex;gap:8px;align-items:center"><input class="input" type="date" name="from" value="{{ $f['from']->format('Y-m-d') }}" aria-label="Dari"><span class="muted">–</span><input class="input" type="date" name="to" value="{{ $f['to']->format('Y-m-d') }}" aria-label="Sampai"></div></div>
    <div><label class="xs muted">Status kunjungan</label>
        <select class="input" name="status"><option value="all">Semua status</option>@foreach (\App\Models\Visit::STATUSES as $k => $l)<option value="{{ $k }}" @selected($f['status'] === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="xs muted">Penanggung jawab</label>
        <select class="input" name="host"><option value="all">Semua penerima</option>@foreach ($hosts as $h)<option value="{{ $h->id }}" @selected($f['host'] === $h->id)>{{ $h->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit" style="height:44px;padding:0 28px">Terapkan</button>
</form>

@php $periodText = $f['from']->translatedFormat('j').($f['from']->format('mY') === $f['to']->format('mY') ? '' : ' '.$f['from']->translatedFormat('F')).'–'.$f['to']->translatedFormat('j F Y'); @endphp
<div class="stats">
    <div class="card stat simple"><div class="l">Total kunjungan</div><div class="n c-blue">{{ $s['total'] }}</div><div class="f">{{ $periodText }}</div></div>
    <div class="card stat simple"><div class="l">Kunjungan selesai</div><div class="n c-green">{{ $s['done'] }}</div><div class="f">{{ $s['percent_done'] }}% dari total kunjungan</div></div>
    <div class="card stat simple"><div class="l">Belum selesai</div><div class="n c-blue">{{ $s['unfinished'] }}</div><div class="f">{{ $s['waiting'] }} menunggu · {{ $s['visiting'] }} berkunjung</div></div>
    <div class="card stat simple"><div class="l">Rata-rata durasi</div><div class="n">{{ $s['avg_duration'] }} menit</div><div class="f">Dari {{ $s['done'] }} kunjungan selesai</div></div>
</div>

<div class="grid-main">
    <div class="card card-flush">
        <div style="padding:22px 22px 14px"><h2>Rekap harian</h2><p class="sub">{{ count($daily) }} hari · seluruh tujuan dan penanggung jawab</p></div>
        <div class="table-wrap" style="max-height:440px;overflow-y:auto">
            <table class="t">
                <thead><tr><th>Tanggal</th><th>Total</th><th>Menunggu</th><th>Berkunjung</th><th>Selesai</th></tr></thead>
                <tbody>
                @foreach ($daily as $d)
                    <tr><td class="b">{{ $d['date']->translatedFormat('d M Y') }}</td><td class="muted">{{ $d['total'] }}</td><td class="muted">{{ $d['waiting'] }}</td><td class="muted">{{ $d['visiting'] }}</td><td class="muted">{{ $d['done'] }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>TOTAL</td><td>{{ $s['total'] }}</td><td>{{ $s['waiting'] }}</td><td>{{ $s['visiting'] }}</td><td>{{ $s['done'] }}</td></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <h2>Data siap diolah</h2>
            <p class="sub" style="margin-bottom:16px">{{ $s['total'] }} baris kunjungan sesuai filter aktif. Pilih format dan kolom sebelum ekspor.</p>
            <a class="btn btn-primary btn-block" href="{{ route('reports.excel', $q) }}"><x-icon name="download" :size="15" /> Unduh Excel (.xlsx)</a>
            <p style="margin-top:16px"><a class="small b" style="color:#157a52" href="{{ route('reports.sheets', $q) }}">Atau kirim langsung ke Google Sheets ↗</a></p>
        </div>
        <div class="card">
            <h2>Ekspor terakhir</h2>
            @if ($last)
                <div style="margin-top:14px"><b>Laporan {{ $last->period_from->translatedFormat('j') }}–{{ $last->period_to->translatedFormat('j M Y') }}</b>
                    <p class="muted small" style="margin-top:6px">{{ ['xlsx' => 'Excel', 'csv' => 'CSV', 'sheets' => 'Google Sheets'][$last->kind] }} · {{ $last->rows_count }} baris · oleh {{ $last->user->name }}</p>
                    <p class="muted xs" style="margin:4px 0 18px">{{ $last->created_at->isToday() ? 'Hari ini' : $last->created_at->translatedFormat('d M Y') }}, {{ $last->created_at->format('H.i') }} WIB</p>
                    @if ($last->kind === 'sheets')<a class="small b" href="{{ $last->url }}" target="_blank" rel="noopener">Buka Google Sheets ↗</a>
                    @elseif ($last->path)<a class="small b" href="{{ route('reports.download', $last) }}">Unduh ulang ↓</a>@endif
                </div>
            @else
                <p class="muted" style="margin-top:12px">Belum ada ekspor.</p>
            @endif
        </div>
    </div>
</div>
<div class="info-box" style="margin-top:22px"><b>Data pribadi tetap terlindungi</b><p>Nomor WhatsApp tidak disertakan secara default. Format tanggal: YYYY-MM-DD · Zona waktu: WIB (Asia/Jakarta).</p></div>
@endsection
