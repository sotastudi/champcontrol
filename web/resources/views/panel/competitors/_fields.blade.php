@php($competitor ??= null)
<div class="row">
    <div class="col-md-6"><x-field name="first_name" label="Nombre" :value="$competitor?->first_name" required /></div>
    <div class="col-md-6"><x-field name="last_name" label="Apellidos" :value="$competitor?->last_name" required /></div>
</div>
<x-field name="club" label="Club" :value="$competitor?->club" />
<div class="row">
    <div class="col-md-4"><x-field name="birth_date" type="date" label="Fecha de nacimiento" :value="$competitor?->birth_date?->format('Y-m-d')" required /></div>
    <div class="col-md-4"><x-select name="sex" label="Sexo" :options="\App\Enums\Sex::options()" :value="$competitor?->sex?->value" placeholder="Selecciona…" required /></div>
    <div class="col-md-4"><x-field name="weight" type="number" step="0.1" min="0" max="300" label="Peso (kg)" :value="$competitor?->weight" /></div>
</div>
<div data-category-fit class="alert alert-warning py-2 d-none" role="status"></div>
<x-select name="level" label="Nivel" :options="\App\Support\MartialLevels::options()" :value="$competitor?->level" placeholder="Selecciona…" required />
<div class="mb-3">
    <label for="photo" class="form-label">Foto del participante (opcional)</label>
    @if ($competitor?->hasPhoto())
        <div class="mb-2"><img src="{{ route('panel.competitors.photo', [$event, $competitor]) }}?v={{ $competitor->updated_at?->timestamp }}" alt="Foto actual" class="img-thumbnail" style="max-height: 10rem"></div>
    @endif
    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
    <div class="form-text">JPG, PNG o WebP, máximo 3 MB. Es un dato privado: no se publica.</div>
    @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($competitor?->hasPhoto())
        <x-check name="remove_photo" label="Quitar la foto actual" :checked="false" />
    @endif
</div>
<div class="row">
    <div class="col-md-6"><x-field name="email" type="email" label="Correo electrónico" :value="$competitor?->email" /></div>
    <div class="col-md-6"><x-field name="phone" label="Teléfono" :value="$competitor?->phone" /></div>
</div>
