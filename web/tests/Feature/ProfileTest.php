<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_profile(): void
    {
        $this->get('/panel/perfil')->assertRedirect(route('login'));
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/panel/perfil', ['name' => 'Nuevo', 'email' => 'nuevo@example.com'])
            ->assertRedirect(route('panel.profile.edit'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nuevo', 'email' => 'nuevo@example.com']);
    }

    public function test_email_must_be_unique_but_can_keep_own(): void
    {
        User::factory()->create(['email' => 'otro@example.com']);
        $user = User::factory()->create(['email' => 'yo@example.com']);

        $this->actingAs($user)->put('/panel/perfil', ['name' => 'Yo', 'email' => 'otro@example.com'])
            ->assertSessionHasErrors('email');
        $this->actingAs($user)->put('/panel/perfil', ['name' => 'Yo', 'email' => 'yo@example.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'clave-segura-1']);

        $this->actingAs($user)->put('/panel/perfil/contrasena', [
            'current_password' => 'incorrecta', 'password' => 'otra-clave-22', 'password_confirmation' => 'otra-clave-22',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/panel/perfil/contrasena', [
            'current_password' => 'clave-segura-1', 'password' => 'otra-clave-22', 'password_confirmation' => 'otra-clave-22',
        ])->assertRedirect(route('panel.profile.edit'));

        $this->assertTrue(Hash::check('otra-clave-22', $user->fresh()->password));
    }
}
