@extends('layouts.admin')
@section('title', 'Tambah tamu')
@section('crumb', 'Tambah tamu')
@section('nav', 'visits')
@section('content')
<a class="back" href="{{ route('visits.index') }}"><x-icon name="arrow-left" :size="14" /> Data kunjungan</a>
<div class="page-head"><div><h1>Tambah tamu</h1><p>Daftarkan tamu secara manual melalui resepsionis.</p></div></div>

<div class="grid-main" style="grid-template-columns:1fr 330px">
    <form class="card" method="POST" action="{{ route('visits.store') }}" novalidate>
        @csrf
        <h2>Identitas tamu</h2><p class="sub" style="margin-bottom:20px">Kolom bertanda * wajib diisi.</p>
        <div class="form-grid">
            <div class="field"><label for="guest_name">Nama lengkap *</label>
                <input id="guest_name" name="guest_name" class="input @error('guest_name') err @enderror" value="{{ old('guest_name') }}" placeholder="Masukkan nama sesuai identitas" required>
                @error('guest_name')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="guest_phone">Nomor WhatsApp *</label>
                <input id="guest_phone" name="guest_phone" type="tel" class="input @error('guest_phone') err @enderror" value="{{ old('guest_phone') }}" placeholder="08xx xxxx xxxx" required>
                @error('guest_phone')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="guest_company">Instansi / perusahaan (opsional)</label>
                <input id="guest_company" name="guest_company" class="input" value="{{ old('guest_company') }}" placeholder="Nama instansi atau perusahaan"></div>
            <div class="field"><label for="guest_email">Email (opsional)</label>
                <input id="guest_email" name="guest_email" type="email" class="input @error('guest_email') err @enderror" value="{{ old('guest_email') }}" placeholder="nama@perusahaan.com">
                @error('guest_email')<div class="error-text">{{ $message }}</div>@enderror</div>
        </div>
        <hr class="sep">
        <h2 style="margin-bottom:18px">Detail kunjungan</h2>
        <div class="form-grid">
            <div class="field"><label for="purpose">Tujuan kunjungan *</label>
                <select id="purpose" name="purpose" class="input @error('purpose') err @enderror" required>
                    <option value="">Pilih tujuan kunjungan</option>
                    @foreach ($purposes as $p)<option value="{{ $p }}" @selected(old('purpose') === $p)>{{ $p }}</option>@endforeach
                </select>@error('purpose')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="host_id">Bertemu dengan *</label>
                <select id="host_id" name="host_id" class="input @error('host_id') err @enderror" required>
                    <option value="">Pilih penanggung jawab</option>
                    @foreach ($hosts as $h)<option value="{{ $h->id }}" @selected((string) old('host_id') === (string) $h->id)>{{ $h->label }}</option>@endforeach
                </select>@error('host_id')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field span2"><label for="notes">Catatan (opsional)</label>
                <input id="notes" name="notes" class="input" value="{{ old('notes') }}" maxlength="500" placeholder="Tambahkan agenda atau informasi lain untuk petugas."></div>
        </div>
        <div class="consent-box">
            <label class="check"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> <span>Tamu telah menyetujui penggunaan data untuk administrasi kunjungan.</span></label>
            @error('consent')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ route('visits.index') }}">Batal</a>
            <button class="btn btn-primary" type="submit">Simpan &amp; beri tahu penerima</button>
        </div>
    </form>

    <div class="stack">
        <div class="card">
            <h2>Pencatatan manual</h2><p class="sub">Untuk tamu yang tidak dapat mengisi formulir melalui QR Code.</p>
            <ol class="steps">
                <li><span class="n">1</span><div><b>Lengkapi identitas tamu</b><small>Pastikan nomor WhatsApp benar.</small></div></li>
                <li><span class="n">2</span><div><b>Pilih penanggung jawab</b><small>Notifikasi dikirim setelah disimpan.</small></div></li>
                <li><span class="n">3</span><div><b>Arahkan ke ruang tunggu</b><small>Tunggu konfirmasi sebelum masuk.</small></div></li>
            </ol>
        </div>
        <div class="info-box"><b>Status awal: {{ \App\Models\Setting::bool('require_confirmation', true) ? 'Menunggu konfirmasi' : 'Berkunjung (konfirmasi otomatis)' }}</b><p>Data yang disimpan akan langsung muncul pada daftar kunjungan hari ini.</p></div>
        <p class="muted small">Data tamu hanya dapat diakses oleh petugas berwenang.</p>
    </div>
</div>
@endsection
