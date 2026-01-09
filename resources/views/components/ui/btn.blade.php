@props([
  'variant' => 'primary',
  'size' => 'sm',
  'href' => null,
  'type' => 'button',
  'disabled' => false,
  'icon' => null,
  'block' => false,
])

@php
  $extra = $attributes->get('class');
  $cls = "btn btn-{$variant} ".($size==='md' ? '' : "btn-{$size}").($block ? ' w-100' : '');
  if ($extra) $cls .= ' '.$extra;
@endphp

@if($href)
  <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>
    @if($icon)<span class="me-1">{!! $icon !!}</span>@endif {{ $slot }}
  </a>
@else
  <button type="{{ $type }}"
          {{ $attributes->merge(['class' => $cls])->when($disabled, fn($a)=>$a->merge(['disabled'=>true])) }}>
    @if($icon)<span class="me-1">{!! $icon !!}</span>@endif {{ $slot }}
  </button>
@endif
