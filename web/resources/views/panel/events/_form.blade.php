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

<fieldset class="mt-4">
    <legend class="h5">Reglas del campeonato</legend>
    <p class="text-muted small">Valen para todas las categorías del evento. Cambiarlas con el evento en marcha recalcula la clasificación al momento; no se modifica ningún combate ni resultado.</p>
    <x-select name="dq_podium_rule" label="Puesto de un descalificado" :options="\App\Enums\DisqualifiedPodiumRule::options()" :value="($event?->dqPodiumRule() ?? \App\Enums\DisqualifiedPodiumRule::Vacant)->value" required />
    <div class="form-text mb-3">«Vacante»: el puesto del descalificado queda sin cubrir y los demás conservan el suyo (se avisa en el panel). «Suben»: los siguientes ascienden un puesto, respetando empates.</div>
    <x-select name="unfought_withdrawal_rule" label="Retirado que no llegó a combatir" :options="\App\Enums\UnfoughtWithdrawalRule::options()" :value="($event?->unfoughtWithdrawalRule() ?? \App\Enums\UnfoughtWithdrawalRule::KeepPlace)->value" required />
    <div class="form-text mb-3">Afecta solo a quien se retira sin haber disputado ningún combate real (los pases y las incomparecencias no cuentan). Quien sí combatió siempre conserva su puesto.</div>
    <x-select name="default_bronze_rule" label="Regla del bronce por defecto" :options="\App\Enums\BronzeRule::options()" :value="($event?->defaultBronzeRule() ?? \App\Enums\BronzeRule::BronzeMatch)->value" required />
    <div class="form-text mb-3">Solo es el valor que se precarga al crear una categoría; cada categoría guarda su propia regla y se puede cambiar en su formulario.</div>
    <x-select name="withdrawal_before_results_rule" label="Baja antes de que haya resultados" :options="\App\Enums\PreResultsWithdrawalRule::options()" :value="($event?->preResultsWithdrawalRule() ?? \App\Enums\PreResultsWithdrawalRule::CancelAndRegenerate)->value" required />
    <div class="form-text mb-3">Con «permitir baja desde el principio», al registrar la baja el cuadro ya no se podrá regenerar.</div>
</fieldset>
