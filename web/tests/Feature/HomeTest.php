<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_spanish_and_offers_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Iniciar sesión');
    }

    public function test_header_has_the_theme_selector_and_user_button_outside_the_collapsible_menu(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $collapseStart = strpos($html, 'id="nav"');
        $this->assertNotFalse($collapseStart);
        $this->assertLessThan($collapseStart, strpos($html, 'id="theme-menu"'), 'el selector de tema va fuera del menú hamburguesa');
        $this->assertLessThan(strpos($html, 'navbar-toggler'), strpos($html, 'id="theme-menu"'), 'a la izquierda de la hamburguesa');

        // Botón de usuario (solo móvil) hacia el inicio de sesión.
        $this->assertMatchesRegularExpression('#<a class="btn btn-outline-secondary btn-sm d-md-none[^>]*href="'.preg_quote(route('login'), '#').'"[^>]*aria-label="Iniciar sesión"#', $html);

        $this->get(route('login'))->assertOk()->assertSee('Registrarse (crear cuenta)')->assertSee(route('register'), false);
    }

    public function test_signed_in_users_get_the_panel_button_in_the_header(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertSee('aria-label="Mi panel"', false);
    }
}
