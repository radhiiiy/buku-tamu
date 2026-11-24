@props(['status', 'label' => null])
<span class="pill pill-{{ $status }}"><i></i>{{ $label ?? (\App\Models\Visit::STATUSES[$status] ?? $status) }}</span>
