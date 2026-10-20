@props(['name', 'label', 'checked' => false, 'help' => null])

<div class="{{ $attributes->get('wrapper', 'mb-3') }}">
    <div class="form-check form-switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" id="{{ $name }}" value="1" @checked(old($name, $checked))>
        <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
    </div>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
