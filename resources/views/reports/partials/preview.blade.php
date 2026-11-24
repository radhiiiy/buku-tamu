<div class="card card-flush" data-cols="{{ count($cols) }}">
    <div style="padding:22px 22px 12px"><h2>Pratinjau data</h2>
        <p class="sub">{{ count($previewRows) }} dari {{ $total }} baris · {{ count($previewHeaders) }} dari {{ count($cols) }} kolom ditampilkan</p></div>
    <div class="table-wrap">
        <table class="t">
            <thead><tr>@foreach ($previewHeaders as $hd)<th>{{ $hd }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($previewRows as $r)
                <tr>@foreach ($r as $c)<td>{{ $c === '' ? '—' : $c }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(1, count($previewHeaders)) }}" class="center muted" style="padding:30px">{{ $cols ? 'Tidak ada data pada periode ini.' : 'Pilih minimal satu kolom.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="muted xs" style="padding:14px 22px">Tanggal: YYYY-MM-DD · Waktu: HH:mm · Zona waktu: WIB</p>
</div>
