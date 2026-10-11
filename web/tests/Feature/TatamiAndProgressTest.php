<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TatamiAndProgressTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Open', 'discipline' => 'Judo', 'starts_on' => '2026-11-20', 'venue' => 'Pabellón', 'status' => 'draft',
            'is_public' => '0', 'registration_form_enabled' => '0', 'tatami_count' => 3,
        ], $o);
    }

    public function test_tatami_count_and_category_assignment_with_referees(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('panel.events.store'), $this->payload())->assertSessionHasNoErrors();
        $event = $user->events()->firstOrFail();
        $this->assertSame(3, $event->tatamiCount());

        $html = $this->actingAs($user)->get(route('panel.events.edit', $event))->getContent();
        preg_match('#<select id="tatami_count".*?</select>#s', $html, $select);
        $this->assertSame(10, substr_count($select[0], '<option'), 'selector de 1 a 10');
        $this->assertStringNotContainsString('<option value="11"', $html);
        $this->assertStringContainsString('<option value="3" selected', $html);

        foreach ([0, 11, 'abc', '2x'] as $bad) {
            $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['tatami_count' => $bad]))->assertSessionHasErrors('tatami_count');
        }

        $this->actingAs($user)->get(route('panel.categories.create', $event))->assertSee('Tatami 3')->assertDontSee('Tatami 4');

        $base = ['name' => 'Cat', 'bronze_rule' => 'bronze_match'];
        $this->actingAs($user)->post(route('panel.categories.store', $event), $base + ['tatami' => 4])->assertSessionHasErrors('tatami');
        foreach ([1, 4, 6, 9] as $bad) {
            $this->actingAs($user)->post(route('panel.categories.store', $event), $base + ['referees' => $bad])->assertSessionHasErrors('referees');
        }
        $this->actingAs($user)->get(route('panel.categories.create', $event))
            ->assertSee('2 jueces (2 centrales)')->assertSee('3 jueces (2 + 1 central)')->assertSee('5 jueces (4 esquinas + 1 central)');
        $this->actingAs($user)->post(route('panel.categories.store', $event), $base + ['tatami' => 3, 'referees' => 5])->assertSessionHasNoErrors();
        $category = $event->categories()->firstOrFail();
        $this->assertSame([3, 5], [$category->tatami, $category->referees]);

        $this->actingAs($user)->get(route('panel.events.show', $event))->assertSee('Tatami 3 (5 jueces)');
        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['tatami_count' => 2]))->assertSessionHasErrors('tatami_count');
        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['tatami_count' => 3]))->assertSessionHasNoErrors();
    }

    public function test_public_event_page_shows_progress_champion_and_owner_sees_unpublished(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['is_public' => true, 'status' => EventStatus::InProgress]);
        $done = Category::factory()->for($event)->create(['name' => 'Cat Final', 'is_published' => true, 'tatami' => 2]);
        $running = Category::factory()->for($event)->create(['name' => 'Cat Curso', 'is_published' => true]);
        $hidden = Category::factory()->for($event)->create(['name' => 'Cat Oculta', 'is_published' => false]);
        foreach ([$done, $running, $hidden] as $c) {
            Registration::factory()->count(2)->confirmed()->forCategory($c)->create();
            app(BracketGenerator::class)->generate($c, fn ($ids) => $ids->values());
        }
        $final = $done->bouts()->firstOrFail();
        app(ResultRecorder::class)->record($final, $final->slot_a_id);
        $champion = $final->fresh()->winner->competitor->displayName();

        $this->get(route('public.events.show', $event))
            ->assertSee('Cat Final')->assertSee('Finalizada')->assertSee($champion)->assertSee('Tatami 2')
            ->assertSee('Cat Curso')->assertSee('0/1 combates')
            ->assertDontSee('Cat Oculta');

        $this->actingAs($user)->get(route('public.events.show', $event))
            ->assertSee('Cat Oculta')->assertSee('Sin publicar')->assertSee(route('panel.brackets.show', [$event, $hidden]), false);
        $this->actingAs(User::factory()->create())->get(route('public.events.show', $event))->assertDontSee('Cat Oculta');
    }

    public function test_bracket_draws_connector_pairs_and_the_podium_shows_steps(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['is_public' => true, 'status' => EventStatus::InProgress]);
        $category = Category::factory()->for($event)->create(['is_published' => true]);
        Registration::factory()->count(4)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))
            ->assertSee('bracket-grid', false)->assertSee('bracket-cell', false);

        $club = $category->registrations()->with('competitor')->firstOrFail()->competitor->club;
        $html = $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->getContent();
        $this->assertStringContainsString('<small class="d-block text-muted">'.e($club).'</small>', $html, 'el club va en su propia línea bajo el nombre');
        $this->assertStringNotContainsString('('.e($club).')', $html);
    }

    public function test_participant_box_shows_number_above_the_name_and_club_below_with_a_fallback(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::RegistrationClosed]);
        $category = Category::factory()->for($event)->create();
        $withClub = Competitor::factory()->create(['event_id' => $event->id, 'first_name' => 'Ana', 'last_name' => 'Con', 'club' => 'Dojo Sur']);
        $noClub = Competitor::factory()->create(['event_id' => $event->id, 'first_name' => 'Bea', 'last_name' => 'Sin', 'club' => null]);
        foreach ([$withClub, $noClub] as $c) {
            Registration::factory()->create(['category_id' => $category->id, 'competitor_id' => $c->id, 'status' => 'confirmed']);
        }
        $this->actingAs($user)->post(route('panel.events.bibs', $event));
        app(BracketGenerator::class)->generate($category);

        $html = $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->getContent();

        $this->assertMatchesRegularExpression('#<small class="d-block text-muted bib-number">Nº \d</small>\s*<span class="side-chip[^>]*>.*?</span></span>\s*Ana Con#s', $html, 'número encima del nombre');
        $this->assertStringContainsString('Dojo Sur</small>', $html);
        $this->assertStringContainsString('text-muted fst-italic">Sin club</small>', $html);
    }
}
