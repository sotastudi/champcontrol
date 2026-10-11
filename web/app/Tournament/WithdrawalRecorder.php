<?php

namespace App\Tournament;

use App\Enums\EventStatus;
use App\Enums\PreResultsWithdrawalRule;
use App\Enums\RegistrationStatus;
use App\Enums\WithdrawalType;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

final class WithdrawalRecorder
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /** @throws TournamentException */
    public function record(Registration $registration, WithdrawalType $type, ?string $reason = null): void
    {
        $registration = $registration->fresh();
        $category = $registration->category;

        if ($category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para registrar bajas.');
        }
        if ($registration->isWithdrawn()) {
            throw new TournamentException('Esta inscripción ya está dada de baja.');
        }
        if ($registration->status !== RegistrationStatus::Confirmed || ! $registration->isInBracket()) {
            throw new TournamentException('Solo se puede dar de baja a un inscrito confirmado que esté en el cuadro.');
        }
        if (! $category->hasResults()) {
            if ($category->event->preResultsWithdrawalRule() === PreResultsWithdrawalRule::CancelAndRegenerate) {
                throw new TournamentException('Aún no hay resultados: cancela la inscripción y regenera el cuadro.');
            }
            if (! $category->isRegenerationLocked() && $category->bracketIsStale()) {
                throw new TournamentException(TournamentException::STALE_BRACKET);
            }
        }

        DB::transaction(function () use ($registration, $type, $reason) {
            $registration->forceFill([
                'withdrawal_type' => $type,
                'withdrawal_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'withdrawn_at' => now(),
            ])->save();

            $this->advancer->resolveWalkovers($registration);
        });
    }
}
