@props(['borrower', 'size' => null])

@if ($borrower->photo_path)
    <img src="{{ $borrower->photo_url }}" alt="{{ $borrower->full_name }}" {{ $attributes->class(['avatar', 'avatar-lg' => $size === 'lg']) }}>
@else
    <span {{ $attributes->class(['avatar', 'avatar-lg' => $size === 'lg']) }}>{{ mb_strtoupper(mb_substr($borrower->first_name, 0, 1).mb_substr($borrower->last_name, 0, 1)) }}</span>
@endif
