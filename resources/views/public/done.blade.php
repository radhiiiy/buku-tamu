@extends('layouts.public')
@section('title', __('public.done_title'))
@section('content')
<div class="p-top">
    <div class="p-brand sapa">sapa.</div>
    <span class="muted small">{{ $office['name'] }}</span>
</div>

<div class="success-ring"><i><x-icon name="check" :size="30" /></i></div>
<h1 class="p-title center">{{ __('public.done_title') }}</h1>
<p class="center muted" style="margin-top:8px">{{ __('public.done_sub', ['name' => explode(' ', $visit->guest_name)[0]]) }}</p>

<div class="receipt">
    <div class="xs muted b">{{ __('public.proof') }}</div>
    <div class="code">#{{ $visit->code }}</div>
    <x-pill :status="$visit->status" :label="__('public.status.'.$visit->status)" />
    <hr>
    <div class="sum-row"><span>{{ __('public.guest') }}</span><b>{{ $visit->guest_name }}</b></div>
    <div class="sum-row"><span>{{ __('public.meeting') }}</span><b>{{ $visit->host->name }}</b></div>
    <div class="sum-row"><span>{{ __('public.goal') }}</span><b>{{ $visit->purpose }}</b></div>
    <div class="sum-row"><span>{{ __('public.arrival') }}</span><b>{{ $visit->arrived_at->translatedFormat('d M Y') }} · {{ $visit->arrived_at->format('H.i') }}</b></div>
</div>

<div class="notice-p">
    <span style="color:var(--blue)"><x-icon name="bell" /></span>
    <div>
        @if ($visit->status === 'menunggu')
            <b>{{ __('public.notified', ['host' => $visit->host->name]) }}</b><small>{{ __('public.please_wait') }}</small>
        @else
            <b>{{ __('public.auto_in') }}</b><small>{{ __('public.auto_in_sub', ['host' => $visit->host->name]) }}</small>
        @endif
    </div>
</div>

<a class="btn btn-dark btn-lg" href="{{ route('public.welcome') }}">{{ __('public.back_home') }} <x-icon name="arrow-right" :size="20" /></a>
<div class="p-foot">{{ __('public.keep_proof') }}</div>
@endsection
