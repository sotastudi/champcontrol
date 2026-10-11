<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Red de seguridad: recorre TODAS las rutas del panel. Si alguien añade una ruta
 * sin auth o sin comprobar la propiedad del evento, o sin fixture aquí, este test falla.
 */
class OrganizerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_panel_route_requires_authentication(): void
    {
        $routes = $this->panelRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri().' sin auth');
        }
    }

    public function test_every_event_scoped_route_rejects_other_organizers(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $params = $this->fixtureParameters($owner);
        $checked = 0;

        foreach ($this->panelRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'panel/eventos/{event}')) {
                $this->assertContains(
                    $route->methods()[0].' '.$route->uri(),
                    ['GET panel/eventos', 'GET panel/eventos/crear', 'POST panel/eventos'],
                    'Ruta del panel fuera de panel/eventos/{event} sin comprobación de propiedad: '.$route->uri(),
                );

                continue;
            }

            $this->assertContains('can:manage,event', $route->gatherMiddleware(), $route->uri().' sin policy');

            $uri = $route->uri();
            foreach ($route->parameterNames() as $name) {
                $this->assertArrayHasKey($name, $params, 'Falta fixture para {'.$name.'} en '.$route->uri());
                $uri = str_replace('{'.$name.'}', (string) $params[$name], $uri);
            }

            $this->actingAs($intruder)->call($route->methods()[0], '/'.$uri)->assertForbidden();
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    /** @return array<string,int> id por nombre de parámetro de ruta, todo perteneciente a $owner */
    private function fixtureParameters(User $owner): array
    {
        $event = Event::factory()->for($owner)->create();

        return ['event' => $event->id];
    }

    /** @return list<LaravelRoute> */
    private function panelRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $r) => str_starts_with($r->uri(), 'panel'),
        ));
    }
}
