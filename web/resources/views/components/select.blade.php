@props(['name', 'label', 'options', 'value' => null, 'required' => false, 'placeholder' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
            {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
