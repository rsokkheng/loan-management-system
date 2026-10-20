@props(['status'])

{{-- Renders any enum exposing label() and color() as a subtle Bootstrap badge. --}}
<span {{ $attributes->class(['badge rounded-pill', "text-bg-{$status->color()}"]) }}>{{ $status->label() }}</span>
