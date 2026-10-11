<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'username'])]
#[Hidden(['password', 'remember_token'])]
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
