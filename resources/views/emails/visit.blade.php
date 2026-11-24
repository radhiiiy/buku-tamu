<!doctype html>
<html lang="id"><body style="font-family:Arial,sans-serif;color:#14264a;line-height:1.5">
<h2 style="margin:0 0 8px">{{ $headline }}</h2>
<p>{{ $line }}</p>
<table cellpadding="6" style="border-collapse:collapse;background:#f4f8ff;border-radius:8px">
<tr><td>Kode</td><td><strong>#{{ $visit->code }}</strong></td></tr>
<tr><td>Tamu</td><td>{{ $visit->guest_name }}{{ $visit->guest_company ? ' · '.$visit->guest_company : '' }}</td></tr>
<tr><td>Tujuan</td><td>{{ $visit->purpose }}</td></tr>
<tr><td>Bertemu</td><td>{{ $visit->host->name }}</td></tr>
<tr><td>Masuk</td><td>{{ $visit->arrived_at->translatedFormat('d M Y') }} · {{ $visit->arrived_at->format('H.i') }} WIB</td></tr>
</table>
<p style="color:#6b7a99;font-size:13px">Email otomatis dari {{ config('app.name') }}.</p>
</body></html>
