@extends('layouts.public')
@section('content')
<div class="p-top">
    <div class="p-brand">TRIMAS VISITOR</div>
    <select class="lang" aria-label="Bahasa" onchange="location='{{ url('/bahasa') }}/'+this.value">
        <option value="id" @selected(app()->getLocale() === 'id')>ID</option>
        <option value="en" @selected(app()->getLocale() === 'en')>EN</option>
    </select>
</div>

<section class="hero">
    <div class="eyebrow">{{ mb_strtoupper(__('public.welcome_at')) }}</div>
    <h2>{{ $office['name'] }}</h2>
    <h1>{{ __('public.hero_title') }}</h1>
    <p>{{ __('public.hero_sub') }}</p>
    <span class="chip"><x-icon name="clock" :size="16" /> {{ __('public.takes') }}</span>
</section>

<section class="p-steps">
    <h3>{{ __('public.steps_title') }}</h3>
    <ul>
        @foreach ([1, 2, 3] as $i)
            <li><span class="n">{{ $i }}</span><div><b>{{ __("public.step{$i}_t") }}</b><small>{{ __("public.step{$i}_d") }}</small></div></li>
        @endforeach
    </ul>
</section>

<a class="btn btn-dark btn-lg" href="{{ route('public.form') }}">{{ __('public.start') }} <x-icon name="arrow-right" :size="20" /></a>
<div class="p-foot">
    <span style="color:#177a52"><x-icon name="shield" :size="14" /></span> {{ __('public.privacy') }}<br>
    <a href="mailto:{{ $office['email'] }}">{{ __('public.help') }}</a>
</div>
@endsection
