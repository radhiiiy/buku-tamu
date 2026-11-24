@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('crumb', 'Pengaturan')
@section('nav', 'settings')
@php
    $me = auth()->user();
    $sections = ['profil' => 'Profil kantor', 'tim' => 'Tim & akses', 'penerima' => 'Penerima notifikasi', 'notifikasi' => 'Notifikasi', 'integrasi' => 'Integrasi', 'privasi' => 'Privasi data'];
@endphp
@section('content')
<div class="page-head"><div><h1>Pengaturan</h1><p>Kelola profil kantor, operasional, dan keamanan data kunjungan.</p></div></div>
<div class="set-grid">
    <nav class="card side-tabs">
        @foreach ($sections as $k => $l)<a class="{{ $section === $k ? 'on' : '' }}" href="{{ route('settings.show', $k) }}">{{ $l }}</a>@endforeach
    </nav>

    <div class="card" style="padding:26px">
    @if ($section === 'profil')
        <form method="POST" action="{{ route('settings.office') }}">@csrf @method('PUT')
            <h2>Profil &amp; operasional kantor</h2><p class="sub" style="margin-bottom:22px">Informasi ini ditampilkan pada buku tamu digital.</p>
            <div class="form-grid">
                <div class="field"><label for="office_name">Nama kantor</label><input id="office_name" name="office_name" class="input @error('office_name') err @enderror" value="{{ old('office_name', $office['name']) }}">@error('office_name')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="office_location">Nama lokasi</label><input id="office_location" name="office_location" class="input" value="{{ old('office_location', $office['location']) }}"></div>
                <div class="field span2"><label for="office_address">Alamat kantor</label><input id="office_address" name="office_address" class="input" value="{{ old('office_address', $office['address']) }}"></div>
                <div class="field"><label for="reception_email">Email resepsionis</label><input id="reception_email" name="reception_email" type="email" class="input @error('reception_email') err @enderror" value="{{ old('reception_email', $office['email']) }}">@error('reception_email')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="tz">Zona waktu</label><select id="tz" class="input" disabled><option>{{ $timezone }}</option></select><p class="hint">Diatur lewat <code>APP_TIMEZONE</code> di .env.</p></div>
            </div>
            <hr class="sep">
            <div class="setting-row"><div><b>Konfirmasi sebelum masuk</b><small>Tamu menunggu persetujuan penanggung jawab.</small></div><label class="toggle"><input type="checkbox" name="require_confirmation" value="1" @checked(old('require_confirmation', $require_confirmation))><span></span></label></div>
            <div class="setting-row"><div><b>Ingatkan check-out</b><small>Tampilkan pengingat untuk kunjungan yang masih aktif.</small></div><label class="toggle"><input type="checkbox" name="checkout_reminder" value="1" @checked(old('checkout_reminder', $checkout_reminder))><span></span></label></div>
            <div class="callout"><b>Akses dan privasi</b><p>Ekspor hanya untuk petugas berizin. Nomor WhatsApp disembunyikan secara default.</p></div>
            <div class="form-actions"><a class="btn" href="{{ route('settings.show', 'profil') }}">Batalkan</a><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
        </form>

    @elseif ($section === 'tim')
        <h2>Tim &amp; akses</h2><p class="sub" style="margin-bottom:18px">Petugas yang dapat masuk ke dasbor.</p>
        <table class="t"><thead><tr><th>Nama</th><th>Peran</th><th>Ekspor nomor WhatsApp</th></tr></thead><tbody>
        @foreach ($members as $m)
            <tr><td><span class="name">{{ $m->name }}</span><small>{{ $m->email }}</small></td><td>{{ $m->roleLabel() }}</td>
            <td>@if ($me->isAdmin())
                <form method="POST" action="{{ route('settings.members.permission', $m) }}">@csrf<button class="btn btn-sm">{{ $m->can_export_phone ? 'Diizinkan · Cabut' : 'Tidak · Izinkan' }}</button></form>
            @else {{ $m->can_export_phone ? 'Diizinkan' : 'Tidak' }} @endif</td></tr>
        @endforeach</tbody></table>
        @if ($me->isAdmin())
            <hr class="sep" style="margin-top:26px"><h2 style="font-size:16px;margin-bottom:14px">Tambah anggota</h2>
            <form method="POST" action="{{ route('settings.members.store') }}" class="form-grid">@csrf
                <div class="field"><label>Nama</label><input name="name" class="input" value="{{ old('name') }}" required>@error('name')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Email</label><input name="email" type="email" class="input" value="{{ old('email') }}" required>@error('email')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label>Peran</label><select name="role" class="input"><option value="resepsionis">Resepsionis</option><option value="admin">Admin</option></select></div>
                <div class="field"><label>Kata sandi (min. 8)</label><input name="password" type="password" class="input" minlength="8" required>@error('password')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="span2 form-actions"><button class="btn btn-primary" type="submit">Tambah anggota</button></div>
            </form>
        @else <p class="hint" style="margin-top:16px">Hanya admin yang dapat mengubah akses tim.</p> @endif

    @elseif ($section === 'penerima')
        <h2>Penerima notifikasi</h2>
        <p class="sub" style="margin-bottom:18px">Orang kantor yang bisa dipilih tamu. Saat tamu memilih seseorang, pemberitahuan dikirim ke email dan nomor WhatsApp orang tersebut.</p>
        @php $mailer = config('mail.default'); @endphp
        @if (in_array($mailer, ['log', 'array'], true))
            <div class="alert alert-warn">Email belum benar-benar terkirim: <code>MAIL_MAILER={{ $mailer }}</code> di .env hanya menulis ke log. Ganti ke <code>smtp</code> dan isi akun pengirim.</div>
        @endif
        @unless ($waEnabled)
            <div class="alert alert-warn">WhatsApp belum aktif: pesan hanya ditulis ke log. Isi <code>WHATSAPP_DRIVER=fonnte</code> dan <code>FONNTE_TOKEN</code> di .env.</div>
        @endunless
        <div class="table-wrap">
        <table class="t"><thead><tr><th>Nama</th><th>Tim / divisi</th><th>Email penerima</th><th>WhatsApp</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($hosts as $h)
            @php $bag = $errors->getBag('host'.$h->id); @endphp
            <tr>
                <td><input form="hf{{ $h->id }}" name="name" class="input" value="{{ $h->name }}" required maxlength="100" aria-label="Nama" @disabled(! $me->isAdmin())></td>
                <td><input form="hf{{ $h->id }}" name="team" class="input" value="{{ $h->team }}" maxlength="100" aria-label="Tim" @disabled(! $me->isAdmin())></td>
                <td><input form="hf{{ $h->id }}" name="email" type="email" class="input @if ($bag->has('email')) err @endif" value="{{ $h->email }}" maxlength="150" placeholder="nama@perusahaan.co.id" aria-label="Email" @disabled(! $me->isAdmin())>
                    @if ($bag->has('email') || $bag->has('name'))<div class="error-text">{{ $bag->first('email') ?: $bag->first('name') }}</div>@elseif (! $h->email)<div class="hint">Belum ada email, hanya notifikasi dasbor.</div>@endif</td>
                <td><input form="hf{{ $h->id }}" name="phone" type="tel" class="input @if ($bag->has('phone')) err @endif" value="{{ $h->phone }}" maxlength="20" placeholder="0812 3456 7890" aria-label="WhatsApp" @disabled(! $me->isAdmin())>
                    @if ($bag->has('phone'))<div class="error-text">{{ $bag->first('phone') }}</div>@endif</td>
                <td><x-pill :status="$h->is_active ? 'berkunjung' : 'menunggu'" :label="$h->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                <td>@if ($me->isAdmin())
                    <div class="cell-act">
                        <button form="hf{{ $h->id }}" class="btn btn-sm btn-primary" type="submit">Simpan</button>
                        <button form="ht{{ $h->id }}" class="btn btn-sm" type="submit">{{ $h->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        @if ($h->visits_count === 0)<button form="hd{{ $h->id }}" class="btn btn-sm" type="submit" onclick="return confirm('Hapus {{ e($h->name) }}?')">Hapus</button>@endif
                    </div>
                    <form id="hf{{ $h->id }}" method="POST" action="{{ route('settings.hosts.update', $h) }}">@csrf @method('PUT')</form>
                    <form id="ht{{ $h->id }}" method="POST" action="{{ route('settings.hosts.toggle', $h) }}">@csrf</form>
                    <form id="hd{{ $h->id }}" method="POST" action="{{ route('settings.hosts.destroy', $h) }}">@csrf @method('DELETE')</form>
                @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center muted" style="padding:30px">Belum ada penerima.</td></tr>
        @endforelse
        </tbody></table>
        </div>
        @if ($me->isAdmin())
            @php $nb = $errors->getBag('hostNew'); @endphp
            <hr class="sep" style="margin-top:26px"><h2 style="font-size:16px;margin-bottom:14px">Tambah penerima</h2>
            <form method="POST" action="{{ route('settings.hosts.store') }}" class="form-grid">@csrf
                <div class="field"><label for="hn_name">Nama</label><input id="hn_name" name="name" class="input @if ($nb->has('name')) err @endif" value="{{ old('name') }}" required maxlength="100">@if ($nb->has('name'))<div class="error-text">{{ $nb->first('name') }}</div>@endif</div>
                <div class="field"><label for="hn_team">Tim / divisi (opsional)</label><input id="hn_team" name="team" class="input" value="{{ old('team') }}" maxlength="100"></div>
                <div class="field span2"><label for="hn_email">Email penerima</label><input id="hn_email" name="email" type="email" class="input @if ($nb->has('email')) err @endif" value="{{ old('email') }}" maxlength="150" placeholder="nama@perusahaan.co.id">@if ($nb->has('email'))<div class="error-text">{{ $nb->first('email') }}</div>@endif</div>
                <div class="field span2"><label for="hn_phone">Nomor WhatsApp (opsional)</label><input id="hn_phone" name="phone" type="tel" class="input @if ($nb->has('phone')) err @endif" value="{{ old('phone') }}" maxlength="20" placeholder="0812 3456 7890">@if ($nb->has('phone'))<div class="error-text">{{ $nb->first('phone') }}</div>@endif</div>
                <div class="span2 form-actions"><button class="btn btn-primary" type="submit">Tambah penerima</button></div>
            </form>
        @else <p class="hint" style="margin-top:16px">Hanya admin yang dapat mengubah daftar penerima.</p> @endif

    @elseif ($section === 'notifikasi')
        <h2>Notifikasi</h2><p class="sub" style="margin-bottom:24px">Preferensi untuk akun {{ $me->name }}. Perubahan tersimpan otomatis.</p>
        @foreach ([['notify_dashboard', 'Notifikasi dashboard', 'Pembaruan tamu secara langsung.'], ['notify_email', 'Notifikasi email', 'Kirim ringkasan ke email akun.'], ['notify_sound', 'Suara notifikasi', 'Bunyikan saat tamu baru datang.']] as [$key, $t, $d])
            <div class="pref"><div><b>{{ $t }}</b><small>{{ $d }}</small></div><label class="toggle"><input type="checkbox" data-pref="{{ $key }}" data-url="{{ route('notifications.prefs') }}" @checked($prefs[$key]) aria-label="{{ $t }}"><span></span></label></div>
        @endforeach

    @elseif ($section === 'integrasi')
        <h2>Integrasi</h2><p class="sub" style="margin-bottom:20px">Hubungkan layanan eksternal untuk ekspor laporan.</p>
        <div class="callout" style="display:flex;justify-content:space-between;align-items:center;gap:14px">
            <div><b>Google Sheets</b><p>{{ $me->googleConnected() ? 'Terhubung sebagai '.$me->google_email : ($googleConfigured ? 'Belum terhubung.' : 'Belum dikonfigurasi di server (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).') }}</p></div>
            @if ($me->googleConnected())
                <form method="POST" action="{{ route('google.disconnect') }}">@csrf<button class="btn btn-sm">Putuskan</button></form>
            @else <a class="btn btn-sm btn-primary" href="{{ route('google.connect') }}" @unless($googleConfigured) style="opacity:.5;pointer-events:none" @endunless>Hubungkan</a> @endif
        </div>

    @else
        <form method="POST" action="{{ route('settings.privacy') }}">@csrf @method('PUT')
            <h2>Privasi data</h2><p class="sub" style="margin-bottom:22px">Kontrol data pribadi tamu pada ekspor.</p>
            <div class="setting-row"><div><b>Izinkan ekspor nomor WhatsApp</b><small>Jika mati, kolom nomor tidak bisa diekspor siapa pun. Jika hidup, hanya akun berizin.</small></div>
                <label class="toggle"><input type="checkbox" name="phone_export_enabled" value="1" @checked($phone_export_enabled) @disabled(! $me->isAdmin())><span></span></label></div>
            <div class="callout"><b>Data tamu</b><p>Nomor WhatsApp hanya terlihat oleh petugas yang login. Ekspor tidak menyertakannya secara default.</p></div>
            @if ($me->isAdmin())<div class="form-actions"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>@else<p class="hint">Hanya admin yang dapat mengubah pengaturan ini.</p>@endif
        </form>
    @endif
    </div>
</div>
@endsection
