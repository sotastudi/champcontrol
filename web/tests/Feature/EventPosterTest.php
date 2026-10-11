<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventPosterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Open de Primavera', 'discipline' => 'Judo', 'starts_on' => '2026-11-20',
            'venue' => 'Pabellón Municipal', 'status' => 'registration_open', 'is_public' => '1', 'registration_form_enabled' => '1',
        ], $o);
    }

    public function test_organizer_uploads_replaces_and_removes_the_poster(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['poster' => UploadedFile::fake()->image('a.jpg', 600, 800)]))
            ->assertSessionHasNoErrors();
        $event = $user->events()->firstOrFail();
        $first = $event->poster_path;
        Storage::disk('local')->assertExists($first);

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['poster' => UploadedFile::fake()->image('b.png', 600, 800)]));
        $second = $event->fresh()->poster_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload());
        $this->assertSame($second, $event->fresh()->poster_path, 'sin fichero ni casilla se conserva');

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['remove_poster' => '1']));
        $this->assertNull($event->fresh()->poster_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_poster_must_be_a_small_raster_image(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        foreach ([
            UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('big.jpg')->size(5000),
        ] as $file) {
            $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['poster' => $file]))->assertSessionHasErrors('poster');
        }
        $this->assertSame(0, $user->events()->count());
    }

    public function test_deleting_the_event_removes_the_poster_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['poster' => UploadedFile::fake()->image('a.jpg')]));
        $event = $user->events()->firstOrFail();
        $path = $event->poster_path;

        $this->actingAs($user)->delete(route('panel.events.destroy', $event));

        Storage::disk('local')->assertMissing($path);
    }

    public function test_public_poster_is_served_only_for_listed_events(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('posters/p.jpg', UploadedFile::fake()->image('p.jpg')->getContent());
        $listed = Event::factory()->create(['is_public' => true, 'status' => EventStatus::RegistrationOpen])->forceFill(['poster_path' => 'posters/p.jpg']);
        $listed->save();
        $private = Event::factory()->create(['is_public' => false, 'status' => EventStatus::RegistrationOpen])->forceFill(['poster_path' => 'posters/p.jpg']);
        $private->save();

        $this->get(route('public.events.poster', $listed))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('public.events.poster', $private))->assertNotFound();
    }

    public function test_home_shows_event_blocks_with_poster_venue_date_and_register_button(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('posters/p.jpg', UploadedFile::fake()->image('p.jpg')->getContent());
        $open = Event::factory()->create([
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
            'name' => 'Copa Abierta', 'venue' => 'Polideportivo Sur', 'starts_on' => '2026-11-20',
        ])->forceFill(['poster_path' => 'posters/p.jpg']);
        $open->save();
        $closed = Event::factory()->create(['is_public' => true, 'status' => EventStatus::InProgress, 'name' => 'Copa En Curso']);

        $this->get(route('home'))
            ->assertSee(route('public.events.poster', $open), false)
            ->assertSee('Polideportivo Sur')->assertSee('20 de noviembre de 2026')
            ->assertSee(route('public.registration.create', $open), false)
            ->assertDontSee(route('public.registration.create', $closed), false)
            ->assertSee('Ver evento');
    }

    public function test_rules_text_and_pdf_are_saved_downloadable_and_removable(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $pdf = UploadedFile::fake()->createWithContent('Normas Open 2026.pdf', "%PDF-1.4\n%fake\n");

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['rules_text' => "Peso: 1 kg\nSin golpes al rostro", 'rules_pdf' => $pdf]))
            ->assertSessionHasNoErrors();
        $event = $user->events()->firstOrFail();
        Storage::disk('local')->assertExists($event->rules_pdf_path);
        $this->assertStringEndsWith('.pdf', $event->rules_pdf_name);

        $this->actingAs($user)->get(route('public.events.rules', $event))->assertOk()
            ->assertHeader('content-disposition');
        $this->get(route('public.events.show', $event))
            ->assertSee('Descargar normas (PDF)')->assertSee('Sin golpes al rostro')->assertSee(route('public.events.rules', $event), false);
        $this->get(route('public.events.rules', $event))->assertOk();

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['remove_rules_pdf' => '1']));
        $this->assertNull($event->fresh()->rules_pdf_path);
        $this->get(route('public.events.rules', $event))->assertNotFound();
    }

    public function test_rules_pdf_must_be_a_pdf_and_private_events_do_not_serve_it(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['rules_pdf' => UploadedFile::fake()->image('x.jpg')]))
            ->assertSessionHasErrors('rules_pdf');
        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['is_public' => '0', 'rules_pdf' => UploadedFile::fake()->createWithContent('n.pdf', "%PDF-1.4\n")]));
        $event = $user->events()->firstOrFail();

        auth()->logout();
        $this->get(route('public.events.rules', $event))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('panel.events.rules', $event))->assertForbidden();
    }
}
