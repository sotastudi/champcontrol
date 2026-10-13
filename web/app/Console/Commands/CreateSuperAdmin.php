<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Crea (o convierte) una cuenta superadmin. No existe registro público para este rol. */
class CreateSuperAdmin extends Command
{
    protected $signature = 'kumitecontrol:superadmin
        {email : Correo de la cuenta}
        {--name=Administrador : Nombre}
        {--username= : Nombre de usuario (opcional)}';

    protected $description = 'Crea o promociona una cuenta superadmin (solo lectura sobre toda la plataforma)';

    public function handle(): int
    {
        $user = User::firstWhere('email', $this->argument('email')) ?? new User(['email' => $this->argument('email')]);
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');
        if (! $user->exists || filled($password)) {
            if (strlen((string) $password) < 8) {
                $this->error('La contraseña debe tener al menos 8 caracteres.');

                return self::FAILURE;
            }
            $user->password = $password;
        }
        if (! $user->exists) {
            $user->name = $this->option('name');
        }
        if ($this->option('username')) {
            $user->username = $this->option('username');
        }
        $user->forceFill(['role' => 'superadmin'])->save();
        $this->info("Superadmin listo: {$user->email}");

        return self::SUCCESS;
    }
}
