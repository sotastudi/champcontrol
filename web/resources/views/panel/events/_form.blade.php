@php($event ??= null)
<x-field name="name" label="Nombre" :value="$event?->name" required />
<x-field name="discipline" label="Disciplina" :value="$event?->discipline" required />
<x-field name="starts_on" type="date" label="Fecha" :value="$event?->starts_on?->format('Y-m-d')" required />
<x-field name="venue" label="Lugar" :value="$event?->venue" required />
<x-textarea name="description" label="Descripción" :value="$event?->description" />
<x-select name="status" label="Estado" :options="\App\Enums\EventStatus::options()" :value="$event?->status?->value ?? 'draft'" required />
<x-check name="is_public" label="Evento público" :checked="$event?->is_public ?? false"
         help="Un evento público y no en borrador aparece en la portada y tiene página pública." />
<x-check name="registration_form_enabled" label="Habilitar formulario público de inscripción" :checked="$event?->registration_form_enabled ?? false"
         help="Solo funciona con el evento público y en estado «Inscripciones abiertas». Ver docs/menores.md." />
