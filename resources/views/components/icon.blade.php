@props(['name', 'size' => 'size-[1.15rem]'])
@php
    $file = resource_path('svg/icons/'.preg_replace('/[^a-z\-]/', '', $name).'.svg');
    $svg = is_file($file) ? file_get_contents($file) : '';
    $svg = preg_replace('/<svg /', '<svg aria-hidden="true" class="'.e($size.' shrink-0 '.$attributes->get('class')).'" ', $svg, 1);
@endphp
{!! $svg !!}
