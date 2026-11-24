@props(['paginator'])
@if ($paginator->total() > 0)
@php
    $cur = $paginator->currentPage(); $last = $paginator->lastPage();
    $pages = collect([1, 2, 3, $cur - 1, $cur, $cur + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
@endphp
<div class="pager-row">
    <span class="muted small">Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} kunjungan</span>
    @if ($last > 1)
    <nav class="pager" aria-label="Halaman">
        @if ($paginator->onFirstPage()) <span class="dim">‹</span> @else <a href="{{ $paginator->url($cur - 1) }}" aria-label="Sebelumnya">‹</a> @endif
        @php $prev = 0; @endphp
        @foreach ($pages as $p)
            @if ($p - $prev > 1) <span class="dim">…</span> @endif
            @if ($p === $cur) <span class="cur" aria-current="page">{{ $p }}</span> @else <a href="{{ $paginator->url($p) }}">{{ $p }}</a> @endif
            @php $prev = $p; @endphp
        @endforeach
        @if ($paginator->hasMorePages()) <a href="{{ $paginator->url($cur + 1) }}" aria-label="Berikutnya">›</a> @else <span class="dim">›</span> @endif
    </nav>
    @endif
</div>
@endif
