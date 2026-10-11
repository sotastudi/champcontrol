@php($competitor ??= null)
<div class="row">
    <div class="col-md-6"><x-field name="guardian_name" label="Nombre del representante" :value="$competitor?->guardian_name" /></div>
    <div class="col-md-6"><x-field name="guardian_contact" label="Contacto del representante (teléfono o correo)" :value="$competitor?->guardian_contact" /></div>
</div>
