<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Tournament\BracketGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listed_event_is_visible_and_on_home(): void
    {
        $event = Event::factory()->create(['is_public' => true, 'status' => EventStatus::RegistrationOpen, 'name' => 'Copa Visible']);

        $this->get(route('home'))->assertSee('Copa Visible');
        $this->get(route('public.events.show', $event))->assertOk()->assertSee('Copa Visible');
    }

    public function test_private_and_draft_events_are_not_public(): void
    {
        $private = Event::factory()->create(['is_public' => false, 'status' => EventStatus::InProgress, 'name' => 'Copa Privada']);
        $draft = Event::factory()->create(['is_public' => true, 'status' => EventStatus::Draft, 'name' => 'Copa Borrador']);

        $this->get(route('home'))->assertDontSee('Copa Privada')->assertDontSee('Copa Borrador');
        $this->get(route('public.events.show', $private))->assertNotFound();
        $this->get(route('public.events.show', $draft))->assertNotFound();
    }

    public function test_registration_window_limits_the_public_form(): void
    {
        $event = Event::factory()->create([
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
            'registration_opens_at' => '2026-11-01 10:00:00', 'registration_closes_at' => '2026-11-10 20:30:00',
        ]);

        foreach ([['2026-10-31 23:59:00', false], ['2026-11-01 10:00:00', true], ['2026-11-10 20:30:00', true], ['2026-11-10 20:31:00', false]] as [$now, $open]) {
            $this->travelTo(Carbon::parse($now));
            $this->assertSame($open, $event->fresh()->acceptsPublicRegistrations(), $now);
            $this->get(route('public.registration.create', $event))->assertStatus($open ? 200 : 404);
        }
    }

    public function test_without_dates_the_window_is_always_open_and_one_sided_windows_work(): void
    {
        $base = ['is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen];
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00'));

        $this->assertTrue(Event::factory()->create($base)->acceptsPublicRegistrations());
        $this->assertTrue(Event::factory()->create($base + ['registration_opens_at' => '2026-11-01 00:00:00'])->acceptsPublicRegistrations());
        $this->assertFalse(Event::factory()->create($base + ['registration_opens_at' => '2026-11-06 00:00:00'])->acceptsPublicRegistrations());
        $this->assertFalse(Event::factory()->create($base + ['registration_closes_at' => '2026-11-04 00:00:00'])->acceptsPublicRegistrations());
    }

    public function test_public_page_shows_the_registration_dates(): void
    {
        $event = Event::factory()->create([
            'is_public' => true, 'status' => EventStatus::RegistrationOpen,
            'registration_opens_at' => '2026-11-01 10:00:00', 'registration_closes_at' => '2026-11-10 20:30:00',
        ]);

        $this->get(route('public.events.show', $event))->assertSee('1 de noviembre de 2026, 10:00')->assertSee('10 de noviembre de 2026, 20:30');
    }

    public function test_home_blocks_show_the_closing_date_when_open_or_the_opening_date_when_not_yet_open(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00'));
        $base = ['is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen];
        $open = Event::factory()->create($base + ['name' => 'Copa Abierta', 'registration_opens_at' => '2026-11-01 10:00:00', 'registration_closes_at' => '2026-11-10 20:30:00']);
        $future = Event::factory()->create($base + ['name' => 'Copa Futura', 'registration_opens_at' => '2026-11-20 09:00:00', 'registration_closes_at' => '2026-11-25 18:00:00']);
        $noDates = Event::factory()->create($base + ['name' => 'Copa Sin Fechas']);
        $closed = Event::factory()->create($base + ['name' => 'Copa Cerrada', 'registration_opens_at' => '2026-10-01 10:00:00', 'registration_closes_at' => '2026-11-02 20:30:00']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        // Debajo del botón de cada bloque: cierre si está abierta; apertura si aún no ha llegado.
        $this->assertMatchesRegularExpression('#Inscribirse</a>\s*<div class="small text-muted text-center mt-2 registration-date">\s*Inscripciones abiertas hasta el 10 de noviembre de 2026, 20:30#', $html);
        $this->assertMatchesRegularExpression('#Ver evento</a>\s*<div class="small text-muted text-center mt-2 registration-date">\s*Las inscripciones abren el 20 de noviembre de 2026, 09:00#', $html);
        $this->assertSame(2, substr_count($html, 'registration-date'), 'sin fechas o ya cerradas no se anuncia nada');
        $this->assertStringNotContainsString('25 de noviembre', $html, 'del evento futuro solo se anuncia la apertura');

        $this->assertNull($noDates->registrationDateNote());
        $this->assertNull($closed->registrationDateNote());
        $this->assertSame('closes', $open->registrationDateNote()['type']);
        $this->assertSame('opens', $future->registrationDateNote()['type']);
    }

    public function test_a_countdown_appears_only_when_less_than_five_days_remain_before_closing(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00', 'Europe/Madrid'));
        $base = ['is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen, 'registration_opens_at' => '2026-11-01 10:00:00'];
        $soon = Event::factory()->create($base + ['name' => 'Copa Pronto', 'registration_closes_at' => '2026-11-08 18:30:15']);
        $far = Event::factory()->create($base + ['name' => 'Copa Lejos', 'registration_closes_at' => '2026-11-12 18:00:00']);
        $last = Event::factory()->create($base + ['name' => 'Copa Último Día', 'registration_closes_at' => '2026-11-05 20:00:00']);

        $this->assertTrue($soon->registrationDateNote()['countdown']);
        $this->assertFalse($far->registrationDateNote()['countdown']);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-countdown-to='), 'solo los que cierran en menos de 5 días');
        $this->assertStringContainsString('Cierra en <strong data-countdown-text>3 d 06:30:15</strong>', $html);
        $this->assertStringContainsString('Cierra en <strong data-countdown-text>08:00:00</strong>', $html, 'sin días el último día');
        $this->assertMatchesRegularExpression('#data-countdown-to="2026-11-08T18:30:15[^"]*"#', $html);
        $this->assertStringContainsString('text-danger fw-semibold', $html, 'en rojo cuando falta menos de un día');

        $this->get(route('public.events.show', $soon))->assertSee('Cierra en', false);
        $this->get(route('public.events.show', $far))->assertDontSee('Cierra en', false);
    }

    public function test_public_event_page_has_poster_left_data_right_then_tatamis_then_categories(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('posters/p.jpg', UploadedFile::fake()->image('p.jpg')->getContent());
        $event = Event::factory()->create(['is_public' => true, 'status' => EventStatus::InProgress, 'name' => 'Copa Hero', 'venue' => 'Pabellón Norte', 'tatami_count' => 1]);
        $event->forceFill(['poster_path' => 'posters/p.jpg'])->save();
        $category = Category::factory()->for($event)->create(['name' => 'Cat Hero', 'tatami' => 1, 'is_published' => true]);
        Registration::factory()->count(2)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category);

        $html = $this->seeResults($event)->get(route('public.events.show', $event))->assertOk()->getContent();

        // Dos columnas: el póster primero (izquierda) y los datos después (derecha); en móvil se apilan.
        $this->assertMatchesRegularExpression('#event-hero row.*?col-12 col-md-5 col-lg-4">\s*<img[^>]*event-poster.*?col-12 col-md-7 col-lg-8 event-info#s', $html);
        foreach (['Fecha', 'Lugar'] as $label) {
            $this->assertStringContainsString('text-uppercase text-muted">'.$label.'</span>', $html);
        }
        $this->assertStringContainsString('Pabellón Norte', $html);

        // Debajo, primero los tatamis y luego las categorías.
        $this->assertLessThan(strpos($html, 'Tatamis ahora'), strpos($html, 'event-info'));
        $this->assertLessThan(strpos($html, 'Cuadros y resultados'), strpos($html, 'Tatamis ahora'));

        // Sin póster, los datos ocupan todo el ancho.
        $event->forceFill(['poster_path' => null])->save();
        $without = $this->seeResults($event)->get(route('public.events.show', $event))->getContent();
        $this->assertStringNotContainsString('event-poster', $without);
        $this->assertStringNotContainsString('col-md-7 col-lg-8', $without);
    }

    public function test_rules_text_sits_under_the_description_in_the_data_column(): void
    {
        $event = Event::factory()->create(['is_public' => true, 'status' => EventStatus::InProgress, 'description' => 'Texto descriptivo del evento', 'rules_text' => "Norma 1: sin golpes al rostro\nNorma 2: casco obligatorio"]);

        $html = $this->get(route('public.events.show', $event))->assertOk()->getContent();

        $this->assertStringContainsString('Norma 2: casco obligatorio', $html);
        $this->assertLessThan(strpos($html, 'Norma 1'), strpos($html, 'Texto descriptivo del evento'), 'las normas van debajo de la descripción');
        $this->assertLessThan(strpos($html, 'event-rules'), strpos($html, 'event-info'), 'dentro de la columna de datos');
        $this->assertSame(1, substr_count($html, 'Norma 1'), 'ya no se repiten más abajo');
        $this->assertLessThan(strpos($html, 'Resultados'), strpos($html, 'Norma 2'));
    }
}
