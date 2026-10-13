<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClubStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'username'])]
#[Hidden(['password', 'remember_token', 'logo_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Cuenta creada por un organizador solo para puntuar tatamis: no crea ni gestiona eventos. */
    public function isTatamiAccount(): bool
    {
        return $this->role === 'tatami';
    }

    /** Cuenta de administración de la plataforma: solo lectura sobre organizadores, clubs, eventos e inscritos. */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** Cuenta de un club: se registra en los campeonatos y, una vez aprobada, inscribe a sus competidores. */
    public function isClubAccount(): bool
    {
        return $this->role === 'club';
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path) && Storage::disk('local')->exists($this->logo_path);
    }

    /** Sustituye el logo (borrando el anterior). */
    public function setLogo(UploadedFile $file): void
    {
        $this->deleteLogo();
        $this->forceFill(['logo_path' => $file->store('club-logos', 'local')])->save();
    }

    public function deleteLogo(): void
    {
        if (filled($this->logo_path)) {
            Storage::disk('local')->delete($this->logo_path);
        }
        $this->forceFill(['logo_path' => null])->save();
    }

    /** Competidores del plantel del club. */
    public function athletes(): HasMany
    {
        return $this->hasMany(ClubAthlete::class);
    }

    /** Registros de este club en los campeonatos (cualquier estado). */
    public function clubRegistrations(): HasMany
    {
        return $this->hasMany(EventClub::class);
    }

    /** Invitaciones de organizadores pendientes de aceptar. */
    public function pendingInvitations()
    {
        return $this->clubRegistrations()->where('status', ClubStatus::Invited->value)->with('event.user');
    }

    /** Registro del club en el evento, si lo hay (cualquier estado). */
    public function clubRegistrationFor(Event $event): ?EventClub
    {
        return EventClub::where('event_id', $event->id)->where('user_id', $this->id)->first();
    }

    /** Tatamis que el usuario controla (puede puntuar su combate actual) en eventos de otros organizadores. */
    public function tatamiOperations(): HasMany
    {
        return $this->hasMany(TatamiOperator::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
