<?php

namespace App\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegistrationService
{
    /**
     * @param  array<string,mixed>  $data  campos de Competitor::FIELDS
     *
     * @throws RegistrationException si el competidor ya está inscrito (y no cancelado) en la categoría
     */
    public function register(Category $category, array $data, RegistrationStatus $status = RegistrationStatus::Pending): Registration
    {
        return DB::transaction(function () use ($category, $data, $status) {
            $competitor = $this->findOrCreateCompetitor($category->event, $data);

            $existing = Registration::where('category_id', $category->id)
                ->where('competitor_id', $competitor->id)->first();

            if ($existing && $existing->status !== RegistrationStatus::Cancelled) {
                throw new RegistrationException('Este competidor ya está inscrito en la categoría.');
            }

            if ($existing) {
                $existing->update(['status' => $status]);

                return $existing;
            }

            return Registration::create([
                'category_id' => $category->id,
                'competitor_id' => $competitor->id,
                'status' => $status,
            ]);
        });
    }

    public function changeStatus(Registration $registration, RegistrationStatus $status): void
    {
        if ($status !== RegistrationStatus::Confirmed
            && $registration->category->hasResults()
            && $registration->isInBracket()) {
            throw new RegistrationException('El cuadro ya tiene resultados: la baja de este participante debe resolverse manualmente según el reglamento.');
        }

        $registration->update(['status' => $status]);
    }

    /** Identidad del competidor dentro del evento: nombre y apellidos normalizados + fecha de nacimiento. */
    private function findOrCreateCompetitor(Event $event, array $data): Competitor
    {
        $first = Competitor::normalizeName($data['first_name']);
        $last = Competitor::normalizeName($data['last_name']);

        $existing = $event->competitors()->whereDate('birth_date', $data['birth_date'])->get()
            ->first(fn (Competitor $c) => Competitor::normalizeName($c->first_name) === $first
                && Competitor::normalizeName($c->last_name) === $last);

        return $existing ?? $event->competitors()->create(array_merge(Arr::only($data, Competitor::FIELDS), [
            'first_name' => Str::squish($data['first_name']),
            'last_name' => Str::squish($data['last_name']),
        ]));
    }
}
