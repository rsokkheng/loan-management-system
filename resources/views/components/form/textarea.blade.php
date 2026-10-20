@props(['name', 'label' => null, 'value' => null, 'required' => false, 'rows' => 3])

<div class="{{ $attributes->get('wrapper', 'mb-3') }}">
    @if ($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except('wrapper')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
