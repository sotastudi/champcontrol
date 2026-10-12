@php($category ??= null)
@php($defaultBronzeRule ??= 'bronze_match')
<x-field name="name" label="Nombre" :value="$category?->name" required />
<x-select name="modality" label="Modalidad" :options="\App\Enums\Modality::options()" :value="$category?->modalityEnum()->value ?? 'kumite'" required />
<x-select name="sex" label="Sexo" :options="\App\Enums\Sex::options()" :value="$category?->sex?->value" placeholder="Mixta / sin restricción" />
<div class="row">
    <div class="col-md-6"><x-field name="min_age" type="number" label="Edad mínima" :value="$category?->min_age" min="0" max="120" /></div>
    <div class="col-md-6"><x-field name="max_age" type="number" label="Edad máxima" :value="$category?->max_age" min="0" max="120" /></div>
</div>
<div class="row">
    <div class="col-md-6"><x-field name="min_weight" type="number" step="0.1" label="Peso mínimo (kg)" :value="$category?->min_weight" min="0" /></div>
    <div class="col-md-6"><x-field name="max_weight" type="number" step="0.1" label="Peso máximo (kg)" :value="$category?->max_weight" min="0" /></div>
</div>
<x-select name="level" label="Nivel" :options="\App\Support\MartialLevels::options()" :value="$category?->level" placeholder="Cualquier nivel" />
<x-select name="bronze_rule" label="Regla del bronce" :options="\App\Enums\BronzeRule::options()" :value="$category?->bronze_rule?->value ?? $defaultBronzeRule" required />
<div class="row">
    <div class="col-md-6"><x-select name="tatami" label="Tatami" :options="array_combine(range(1, $event->tatamiCount()), array_map(fn ($n) => 'Tatami '.$n, range(1, $event->tatamiCount())))" :value="$category?->tatami" placeholder="Sin asignar" /></div>
    <div class="col-md-6"><x-select name="referees" label="Jueces en el tatami" :options="[2 => '2 jueces (2 centrales)', 3 => '3 jueces (2 + 1 central)', 5 => '5 jueces (4 esquinas + 1 central)']" :value="$category?->referees" placeholder="Sin indicar (3 por defecto)" /></div>
</div>
<x-select name="draw_criteria" label="Criterios del sorteo" :options="\App\Enums\DrawCriteria::options()" :value="$category?->draw_criteria?->value" :placeholder="'Según el evento (' . $event->drawCriteria()->label() . ')'" />
<x-check name="show_fourth" label="Mostrar también el 4.º puesto" :checked="$category?->show_fourth ?? false"
         help="Solo aplica con combate por el bronce." />
