@extends('layouts.public')
@section('title', __('public.guestbook'))
@section('content')
<div class="p-back">
    <a class="sq" href="{{ route('public.welcome') }}" aria-label="Kembali"><x-icon name="arrow-left" /></a>
    <b>{{ __('public.guestbook') }}</b>
    <a class="help" href="mailto:{{ $office['email'] }}" aria-label="Bantuan"><x-icon name="help" :size="22" /></a>
</div>
<h1 class="p-title">{{ __('public.form_title') }}</h1>
<p class="p-sub">{{ __('public.form_sub') }}</p>
<div class="progress"><i id="progress-bar"></i></div>

<form id="visit-form" method="POST" action="{{ route('public.store') }}" novalidate>
    @csrf
    <div class="sec">{{ __('public.sec1') }}</div>
    <div class="field">
        <label for="guest_name">{{ __('public.name') }} *</label>
        <input id="guest_name" name="guest_name" class="input @error('guest_name') err @enderror" value="{{ old('guest_name') }}" autocomplete="name" maxlength="120" required data-req>
        @error('guest_name') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="guest_phone">{{ __('public.phone') }} *</label>
        <input id="guest_phone" name="guest_phone" type="tel" inputmode="tel" class="input @error('guest_phone') err @enderror" value="{{ old('guest_phone') }}" placeholder="08xx xxxx xxxx" autocomplete="tel" required data-req>
        @error('guest_phone') <div class="error-text">{{ $message }}</div> @else <div class="hint">{{ __('public.phone_hint') }}</div> @enderror
    </div>
    <div class="field">
        <label for="guest_company">{{ __('public.company') }}</label>
        <input id="guest_company" name="guest_company" class="input" value="{{ old('guest_company') }}" maxlength="150" autocomplete="organization">
    </div>

    <div class="sec" style="margin-top:22px">{{ __('public.sec2') }}</div>
    <div class="field">
        <label for="purpose">{{ __('public.purpose') }} *</label>
        <select id="purpose" name="purpose" class="input @error('purpose') err @enderror" required data-req>
            <option value="">{{ __('public.choose') }}</option>
            @foreach ($purposes as $p) <option value="{{ $p }}" @selected(old('purpose') === $p)>{{ $p }}</option> @endforeach
        </select>
        @error('purpose') <div class="error-text">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="host_id">{{ __('public.host') }} *</label>
        <select id="host_id" name="host_id" class="input @error('host_id') err @enderror" required data-req>
            <option value="">{{ __('public.choose') }}</option>
            @foreach ($hosts as $h) <option value="{{ $h->id }}" @selected((string) old('host_id') === (string) $h->id)>{{ $h->label }}</option> @endforeach
        </select>
        @error('host_id') <div class="error-text">{{ $message }}</div> @enderror
    </div>

    <label class="check" style="margin:22px 0 18px">
        <input type="checkbox" name="consent" value="1" @checked(old('consent')) data-req>
        <span>{{ __('public.consent') }}</span>
    </label>
    @error('consent') <div class="error-text" style="margin:-10px 0 14px">{{ $message }}</div> @enderror

    <button type="submit" class="btn btn-dark btn-lg btn-block">{{ __('public.submit') }} <x-icon name="arrow-right" :size="20" /></button>
    <p class="center muted xs" style="margin-top:14px">{{ __('public.form_foot') }}</p>
</form>
@endsection
@push('scripts') <script src="{{ asset('js/public.js') }}" defer></script> @endpush
