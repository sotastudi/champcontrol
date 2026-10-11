<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Enums\Sex;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Registration;
use App\Support\MartialLevels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Crea inscritos ficticios en una categoría para probar el sorteo. Solo toca los marcados con la nota «DEMO». */
class DemoRegistrations extends Command
{
    private const MARK = 'DEMO';

    protected $signature = 'champcontrol:demo-inscritos
        {category : ID de la categoría}
        {--count=12 : Número de inscritos a crear}
        {--extras : Añade también club y peso (para probar esos criterios del sorteo)}
        {--clubs=4 : Con --extras, número de clubes distintos (menos clubes = más coincidencias)}
        {--pending=0 : Cuántos de ellos se dejan pendientes (el resto, confirmados)}
        {--seed= : Semilla para repetir los mismos datos}
        {--clean : Solo borra los inscritos DEMO de la categoría}
        {--force : Permitir en producción}';

    protected $description = 'Crea inscritos de prueba (marcados DEMO) en una categoría para probar el sorteo';

    private const FIRST = ['Aitor', 'Marta', 'Jordi', 'Lucía', 'Pau', 'Nuria', 'Hugo', 'Carla', 'Iván', 'Sara', 'Marc', 'Elena', 'Álex', 'Júlia', 'Dani', 'Irene', 'Raúl', 'Berta', 'Oriol', 'Paula'];

    private const LAST = ['García', 'Martínez', 'López', 'Sánchez', 'Vidal', 'Roca', 'Puig', 'Serra', 'Mora', 'Díez', 'Ferrer', 'Soler', 'Vila', 'Pons', 'Ramos', 'Gil', 'Navarro', 'Costa', 'Prat', 'Ortega'];

    private const CLUBS = ['Dojo Norte', 'Club Sakura', 'Karate Sur', 'Budokan Este', 'Judo Centro', 'Shotokan Oeste', 'Kodokan Mar', 'Dojo Montaña'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Entorno de producción: no se crean datos de prueba (usa --force si es intencionado).');

            return self::FAILURE;
        }

        $category = Category::with('event')->find($this->argument('category'));
        if (! $category) {
            $this->error('No existe la categoría '.$this->argument('category').'.');

            return self::FAILURE;
        }
        if ($category->isRegenerationLocked()) {
            $this->error('La categoría ya tiene resultados o bajas: no se tocan sus inscripciones.');

            return self::FAILURE;
        }

        $removed = $this->clean($category);
        if ($removed > 0) {
            $this->info("Borrados {$removed} inscritos DEMO anteriores.");
        }
        if ($this->option('clean')) {
            return self::SUCCESS;
        }

        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }

        $count = max(1, (int) $this->option('count'));
        $pending = min($count, max(0, (int) $this->option('pending')));
        $clubs = array_slice(self::CLUBS, 0, max(1, min(count(self::CLUBS), (int) $this->option('clubs'))));

        DB::transaction(function () use ($category, $count, $pending, $clubs) {
            for ($i = 0; $i < $count; $i++) {
                $competitor = (new Competitor($this->data($category, $i, $clubs)))->forceFill(['event_id' => $category->event_id]);
                $competitor->save();
                Registration::create([
                    'category_id' => $category->id,
                    'competitor_id' => $competitor->id,
                    'status' => $i < $count - $pending ? RegistrationStatus::Confirmed : RegistrationStatus::Pending,
                ]);
            }
        });

        $this->info("Creados {$count} inscritos DEMO en «{$category->name}» (".($count - $pending).' confirmados, '.$pending.' pendientes).');
        $this->line('Genera el cuadro en el panel para probar el sorteo. Para limpiar: php artisan champcontrol:demo-inscritos '.$category->id.' --clean');

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function data(Category $category, int $i, array $clubs): array
    {
        $date = $category->event->starts_on;
        $age = mt_rand($category->min_age ?? 14, $category->max_age ?? 40);
        $birth = $date->copy()->subYears($age)->subDays(mt_rand(0, 364));
        $sex = $category->sex?->value ?? (mt_rand(0, 1) ? Sex::Male->value : Sex::Female->value);
        $levels = MartialLevels::values();
        $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
        $last = self::LAST[mt_rand(0, count(self::LAST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
        $minW = $category->min_weight ?? 50;
        $maxW = $category->max_weight ?? 90;

        // Solo los datos obligatorios (nombre, apellidos, nacimiento, sexo, nivel); club y peso con --extras.
        $data = [
            'first_name' => $first,
            'last_name' => $last,
            'birth_date' => $birth->toDateString(),
            'sex' => $sex,
            'level' => $levels[mt_rand(0, count($levels) - 1)],
            'notes' => self::MARK,
        ];

        if ($this->option('extras')) {
            $data['club'] = $clubs[$i % count($clubs)];
            $data['weight'] = round($minW + mt_rand(0, 100) / 100 * ($maxW - $minW), 1);
        }

        return $data;
    }

    private function clean(Category $category): int
    {
        $registrations = Registration::where('category_id', $category->id)
            ->whereHas('competitor', fn ($q) => $q->where('notes', self::MARK))->get();

        DB::transaction(function () use ($registrations) {
            $competitorIds = $registrations->pluck('competitor_id');
            Registration::whereIn('id', $registrations->pluck('id'))->delete();
            Competitor::whereIn('id', $competitorIds)->where('notes', self::MARK)->doesntHave('registrations')->delete();
        });

        return $registrations->count();
    }
}
