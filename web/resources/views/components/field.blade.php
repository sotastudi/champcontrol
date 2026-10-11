@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}" @required($required)
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
