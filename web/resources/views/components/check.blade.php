@props(['name', 'label', 'checked' => false, 'help' => null])
<div class="form-check mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" class="form-check-input" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked))>
    <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
