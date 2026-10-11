<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinorsPanelTest extends TestCase
{
    use RefreshDatabase;

    private function setUpEvent(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['starts_on' => '2026-11-20']);

        return [$user, $event, Category::factory()->for($event)->create()];
    }

    private function data(Category $category, array $o = []): array
    {
        return array_merge([
            'first_name' => 'Pau', 'last_name' => 'Vidal Roca', 'birth_date' => '2012-05-05', 'sex' => 'male', 'level' => '5º kyu',
            'category_id' => $category->id, 'status' => 'confirmed',
        ], $o);
    }

    public function test_organizer_can_register_a_minor_without_guardian_and_sees_the_warning_badge(): void
    {
        [$user, $event, $category] = $this->setUpEvent();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category))
            ->assertSessionHasNoErrors()->assertRedirect(route('panel.registrations.index', $event));

        $this->actingAs($user)->get(route('panel.registrations.index', $event))
            ->assertSee('Menor sin datos de representante');
    }

    public function test_minor_with_guardian_data_shows_minor_badge_without_warning(): void
    {
        [$user, $event, $category] = $this->setUpEvent();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, [
            'guardian_name' => 'Rosa Roca', 'guardian_contact' => '600111222', 'guardian_consent' => '1',
        ]))->assertSessionHasNoErrors();

        $competitor = Competitor::firstOrFail();
        $this->assertSame('Rosa Roca', $competitor->guardian_name);
        $this->assertNotNull($competitor->guardian_consent_at);
        $this->actingAs($user)->get(route('panel.registrations.index', $event))
            ->assertSee('Menor')->assertDontSee('Menor sin datos de representante');
    }

    public function test_adult_has_no_minor_badge_and_consent_checkbox_without_name_is_not_recorded(): void
    {
        [$user, $event, $category] = $this->setUpEvent();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, [
            'birth_date' => '1990-01-01', 'guardian_consent' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Competitor::firstOrFail()->guardian_consent_at);
        $this->actingAs($user)->get(route('panel.registrations.index', $event))->assertDontSee('Menor');
    }

    public function test_guardian_fields_can_be_edited(): void
    {
        [$user, $event] = $this->setUpEvent();
        $competitor = Competitor::factory()->for($event)->create(['birth_date' => '2012-05-05']);

        $this->actingAs($user)->get(route('panel.competitors.edit', [$event, $competitor]))
            ->assertOk()->assertSee('guardian_name', false)->assertDontSee('guardian_consent', false);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), [
            'first_name' => $competitor->first_name, 'last_name' => $competitor->last_name, 'birth_date' => '2012-05-05', 'sex' => 'male', 'level' => '5º kyu',
            'guardian_name' => 'Rosa Roca', 'guardian_contact' => 'rosa@familia.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rosa@familia.test', $competitor->fresh()->guardian_contact);
    }

    public function test_clearing_the_guardian_name_also_clears_the_recorded_consent(): void
    {
        [$user, $event] = $this->setUpEvent();
        $competitor = Competitor::factory()->for($event)->create(['birth_date' => '2012-05-05', 'guardian_name' => 'Rosa Roca', 'guardian_contact' => '600']);
        $competitor->forceFill(['guardian_consent_at' => now()])->save();

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), [
            'first_name' => $competitor->first_name, 'last_name' => $competitor->last_name, 'birth_date' => '2012-05-05', 'sex' => 'male', 'level' => '5º kyu',
            'guardian_name' => '', 'guardian_contact' => '600',
        ])->assertSessionHasNoErrors();

        $this->assertNull($competitor->fresh()->guardian_consent_at);
    }

    public function test_csv_includes_minor_and_guardian_columns(): void
    {
        [$user, $event, $category] = $this->setUpEvent();
        $minor = Competitor::factory()->for($event)->create(['last_name' => 'Menor', 'birth_date' => '2012-05-05', 'guardian_name' => 'Rosa Roca', 'guardian_contact' => '=cmd']);
        $minor->forceFill(['guardian_consent_at' => '2026-10-01 09:30:00'])->save();
        $adult = Competitor::factory()->for($event)->create(['last_name' => 'Adulto', 'birth_date' => '1990-01-01']);
        Registration::factory()->confirmed()->create(['category_id' => $category->id, 'competitor_id' => $minor->id]);
        Registration::factory()->confirmed()->create(['category_id' => $category->id, 'competitor_id' => $adult->id]);

        $csv = $this->actingAs($user)->get(route('panel.exports.registrations', $event))->streamedContent();

        $this->assertStringContainsString('Estado;Menor;Representante;"Contacto representante";Consentimiento', $csv);
        $this->assertStringContainsString(";Sí;\"Rosa Roca\";'=cmd;\"01/10/2026 09:30\"", $csv);
        $this->assertMatchesRegularExpression('/Adulto.*;No;;;\R/', $csv);
    }

    public function test_birth_date_after_the_event_date_is_rejected(): void
    {
        [$user, $event, $category] = $this->setUpEvent();
        $event->forceFill(['starts_on' => now()->subDays(10)->toDateString()])->save();
        $eventDay = $event->starts_on;

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['birth_date' => $eventDay->copy()->addDay()->toDateString()]))
            ->assertSessionHasErrors('birth_date');
        $this->assertSame(0, Registration::count());

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['birth_date' => $eventDay->toDateString()]))
            ->assertSessionDoesntHaveErrors('birth_date');
    }

    public function test_negative_age_gets_a_clear_mismatch_message(): void
    {
        [, , $category] = $this->setUpEvent();
        $competitor = new Competitor(['birth_date' => '2030-01-01']);

        $this->assertSame(['Fecha de nacimiento posterior a la fecha del evento'], $category->mismatchesFor($competitor));
    }
}
