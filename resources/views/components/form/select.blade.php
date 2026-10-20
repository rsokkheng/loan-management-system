@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])

@php
    $id = $attributes->get('id', $name);
    $selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div class="{{ $attributes->get('wrapper', 'mb-3') }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required)
        {{ $attributes->except(['id', 'wrapper'])->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
