<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_public_and_shows_the_three_guides_and_the_faq(): void
    {
        $this->get(route('help'))->assertOk()
            ->assertSee('Soy organizador')->assertSee('Soy un club')->assertSee('Soy controlador de tatami')
            ->assertSee('Preguntas frecuentes')->assertSee('Buscar en la ayuda')
            ->assertSee('Crea tu cuenta de organizador')->assertSee('Añade tus competidores')->assertSee('Puntúa el combate');
    }

    public function test_help_icon_is_in_the_header_for_guests_and_users(): void
    {
        $this->get('/')->assertSee('aria-label="Ayuda"', false)->assertSee('href="#help"', false)->assertSee(route('help'), false);
        $this->actingAs(User::factory()->create())->get('/')->assertSee('aria-label="Ayuda"', false);
    }

    public function test_search_finds_steps_and_faqs_without_caring_about_case_or_accents(): void
    {
        $this->get(route('help', ['q' => 'MENORES']))->assertOk()->assertSee('resultados para', false)->assertSee('Cómo inscribo a un menor');
        $this->get(route('help', ['q' => 'representante']))->assertSee('<mark>', false);
        $this->get(route('help', ['q' => 'numeros asignar']))->assertSee('Cierra las inscripciones y asigna los números', false);
        $this->get(route('help', ['q' => 'invitar club']))->assertSee('invita', false);
    }

    public function test_search_without_results_shows_a_message_and_empty_search_shows_everything(): void
    {
        $this->get(route('help', ['q' => 'xyzzy-inexistente']))->assertOk()->assertSee('Sin resultados')->assertSee('No hemos encontrado nada');
        $this->get(route('help', ['q' => '  ']))->assertOk()->assertDontSee('Sin resultados')->assertSee('Soy organizador');
    }

    public function test_search_input_is_escaped(): void
    {
        $this->get(route('help', ['q' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_guide_of_the_current_profile_is_highlighted(): void
    {
        $event = Event::factory()->registrationOpen()->create();
        $this->actingAs($this->clubFor($event))->get(route('help'))->assertSee('Tu perfil');
        $this->get(route('help'))->assertSee('btn btn-sm btn-primary">Soy un club', false);
        auth()->logout();
        $this->get(route('help'))->assertDontSee('Tu perfil');
    }
}
