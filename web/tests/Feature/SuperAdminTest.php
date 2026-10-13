<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_command_creates_a_superadmin(): void
    {
        $this->artisan('kumitecontrol:superadmin', ['email' => 'root@example.com'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'secret-pass-1')
            ->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'root@example.com')->isSuperAdmin());
    }

    public function test_superadmin_sees_organizers_clubs_and_registrations(): void
    {
        $organizer = User::factory()->create(['name' => 'Org Uno']);
        User::factory()->create(['name' => 'Club Norte', 'role' => 'club']);
        $event = Event::factory()->for($organizer)->create(['name' => 'Copa Test']);
        $registration = Registration::factory()->for(Category::factory()->for($event))->create();

        $this->actingAs($this->superAdmin())->get(route('panel.admin.index'))
            ->assertOk()->assertSee('Org Uno')->assertSee('Club Norte')->assertSee('Copa Test')
            ->assertSee($registration->competitor->displayName());
    }

    public function test_events_index_redirects_superadmin_and_blocks_creating_events(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('panel.events.index'))->assertRedirect(route('panel.admin.index'));
        $this->get(route('panel.events.create'))->assertForbidden();
    }

    public function test_other_roles_cannot_open_admin_panel(): void
    {
        foreach (['organizer', 'club', 'tatami'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('panel.admin.index'))->assertForbidden();
        }
    }

    public function test_superadmin_cannot_manage_foreign_events(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($this->superAdmin())->get(route('panel.events.show', $event))->assertForbidden();
    }
}
