@props(['icon' => 'inbox', 'title' => null, 'colspan' => null])

@php $title ??= __('Nothing here yet'); @endphp

@if ($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="text-center text-secondary py-5">
            <i class="bi bi-{{ $icon }} fs-1 d-block mb-2 opacity-50"></i>
            <div class="fw-semibold">{{ $title }}</div>
            @if ($slot->isNotEmpty())<div class="small mt-1">{{ $slot }}</div>@endif
        </td>
    </tr>
@else
    <div class="text-center text-secondary py-5">
        <i class="bi bi-{{ $icon }} fs-1 d-block mb-2 opacity-50"></i>
        <div class="fw-semibold">{{ $title }}</div>
        @if ($slot->isNotEmpty())<div class="small mt-1">{{ $slot }}</div>@endif
    </div>
@endif
