@php($category ??= null)
@php($defaultBronzeRule ??= 'bronze_match')
<x-field name="name" label="Nombre" :value="$category?->name" required />
<x-field name="modality" label="Modalidad" :value="$category?->modality" />
<x-select name="sex" label="Sexo" :options="\App\Enums\Sex::options()" :value="$category?->sex?->value" placeholder="Mixta / sin restricción" />
<div class="row">
    <div class="col-md-6"><x-field name="min_age" type="number" label="Edad mínima" :value="$category?->min_age" min="0" max="120" /></div>
    <div class="col-md-6"><x-field name="max_age" type="number" label="Edad máxima" :value="$category?->max_age" min="0" max="120" /></div>
</div>
<div class="row">
    <div class="col-md-6"><x-field name="min_weight" type="number" step="0.1" label="Peso mínimo (kg)" :value="$category?->min_weight" min="0" /></div>
    <div class="col-md-6"><x-field name="max_weight" type="number" step="0.1" label="Peso máximo (kg)" :value="$category?->max_weight" min="0" /></div>
</div>
<x-field name="level" label="Nivel" :value="$category?->level" />
<x-select name="bronze_rule" label="Regla del bronce" :options="\App\Enums\BronzeRule::options()" :value="$category?->bronze_rule?->value ?? $defaultBronzeRule" required />
<x-check name="show_fourth" label="Mostrar también el 4.º puesto" :checked="$category?->show_fourth ?? false"
         help="Solo aplica con combate por el bronce." />
