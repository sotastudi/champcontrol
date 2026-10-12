@php($athlete ??= null)
<div class="row">
    <div class="col-md-6"><x-field name="first_name" label="Nombre" :value="$athlete?->first_name" required /></div>
    <div class="col-md-6"><x-field name="last_name" label="Apellidos" :value="$athlete?->last_name" required /></div>
</div>
<div class="row">
    <div class="col-md-4"><x-field name="birth_date" type="date" label="Fecha de nacimiento" :value="$athlete?->birth_date?->format('Y-m-d')" required /></div>
    <div class="col-md-4"><x-select name="sex" label="Sexo" :options="\App\Enums\Sex::options()" :value="$athlete?->sex?->value" placeholder="Selecciona…" required /></div>
    <div class="col-md-4"><x-field name="weight" type="number" step="0.1" min="0" max="300" label="Peso (kg)" :value="$athlete?->weight" /></div>
</div>
<x-select name="level" label="Nivel" :options="\App\Support\MartialLevels::options()" :value="$athlete?->level" placeholder="Selecciona…" required />
<div class="row">
    <div class="col-md-6"><x-field name="email" type="email" label="Correo electrónico" :value="$athlete?->email" /></div>
    <div class="col-md-6"><x-field name="phone" label="Teléfono" :value="$athlete?->phone" /></div>
</div>
<fieldset class="border rounded p-3 mb-3">
    <legend class="h6 float-none px-2 w-auto">Representante legal (si es menor)</legend>
    <div class="row">
        <div class="col-md-6"><x-field name="guardian_name" label="Nombre del representante" :value="$athlete?->guardian_name" /></div>
        <div class="col-md-6"><x-field name="guardian_contact" label="Contacto (teléfono o correo)" :value="$athlete?->guardian_contact" /></div>
    </div>
    <x-check name="guardian_consent" :checked="$athlete?->guardian_consent_at !== null" label="Declaro ser el padre, madre o representante legal del menor y consiento su inscripción y el tratamiento de sus datos en los campeonatos en que participe este club." help="Sin este consentimiento un menor no se puede agregar a un campeonato. Los datos del representante no se publican." />
</fieldset>
