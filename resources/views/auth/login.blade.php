<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="login-wrap">
    <form class="card login-card" method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="brand" style="padding:0;color:var(--navy);margin-bottom:4px">Dasbord</div>
        <p class="muted" style="margin-bottom:22px">Masuk sebagai resepsionis untuk mengelola kunjungan.</p>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" class="input @error('email') err @enderror" value="{{ old('email') }}" autocomplete="username" autofocus required>
            @error('email') <div class="error-text">{{ $message }}</div> @enderror
        </div>
        <div class="field">
            <label for="password">Kata sandi</label>
            <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
        </div>
        <label class="check" style="margin-bottom:18px"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
        <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        <p class="center muted small" style="margin-top:16px"><a href="{{ route('public.welcome') }}">← Ke halaman buku tamu</a></p>
    </form>
</div>
</body>
</html>
