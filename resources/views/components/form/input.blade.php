@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'prepend' => null, 'append' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $current = $type === 'password' || $type === 'file' ? null : old($name, $value);
@endphp

<div class="{{ $attributes->get('wrapper', 'mb-3') }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    @if ($prepend || $append)
        <div class="input-group {{ $hasError ? 'has-validation' : '' }}">
            @if ($prepend)<span class="input-group-text">{{ $prepend }}</span>@endif
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" @if (! is_null($current)) value="{{ $current }}" @endif
        @required($required)
        {{ $attributes->except(['id', 'wrapper'])->class(['form-control', 'is-invalid' => $hasError]) }}>
    @if ($append)<span class="input-group-text">{{ $append }}</span>@endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($prepend || $append)
        </div>
    @endif
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
