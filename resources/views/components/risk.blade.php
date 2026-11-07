@props(['level'])
@php
    $map = [
        'rendah' => ['Risiko rendah', 'check-circle'],
        'sedang' => ['Risiko sedang', 'minus-circle'],
        'tinggi' => ['Risiko tinggi', 'warning'],
    ];
    [$label, $icon] = $map[$level] ?? ['Belum dihitung', 'info'];
    $key = isset($map[$level]) ? $level : 'belum';
@endphp
<span {{ $attributes->class(['risk risk-chip', 'risk-'.$key]) }}><x-icon :name="$icon" /> {{ $label }}</span>
