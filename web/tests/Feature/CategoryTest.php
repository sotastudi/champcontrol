<?php

namespace Tests\Feature;

use App\Enums\BronzeRule;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Sub-18 -60kg', 'modality' => 'Combate', 'sex' => 'male', 'min_age' => 15, 'max_age' => 17,
            'min_weight' => 55, 'max_weight' => 60, 'level' => '5º kyu',
            'bronze_rule' => 'bronze_match', 'show_fourth' => '1',
        ], $o);
    }

    public function test_level_is_a_select_of_kyu_and_dan_grades(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->get(route('panel.categories.create', $event))
            ->assertSee('<select id="level"', false)
            ->assertSeeInOrder(['10º kyu', '1º kyu', '1º dan', '10º dan']);

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload(['level' => 'Cinturón verde']))
            ->assertSessionHasErrors('level');
        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload(['level' => '10º dan']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('10º dan', $event->categories()->firstOrFail()->level);
    }

    public function test_owner_creates_updates_and_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload())
            ->assertRedirect(route('panel.events.show', $event));
        $category = $event->categories()->firstOrFail();
        $this->assertSame(BronzeRule::BronzeMatch, $category->bronze_rule);
        $this->assertTrue($category->show_fourth);

        $this->actingAs($user)->put(route('panel.categories.update', [$event, $category]), $this->payload(['name' => 'Otro']))
            ->assertRedirect(route('panel.events.show', $event));
        $this->assertSame('Otro', $category->fresh()->name);

        $this->actingAs($user)->delete(route('panel.categories.destroy', [$event, $category]));
        $this->assertModelMissing($category);
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload([
            'name' => '', 'min_age' => 20, 'max_age' => 10, 'min_weight' => 70, 'max_weight' => 60, 'bronze_rule' => 'x',
        ]))->assertSessionHasErrors(['name', 'max_age', 'max_weight', 'bronze_rule']);
    }

    public function test_open_ended_categories_with_only_min_or_only_max_are_accepted(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload([
            'name' => 'Sub-18', 'min_age' => '', 'max_age' => 17, 'min_weight' => '', 'max_weight' => 60,
        ]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload([
            'name' => '+80 kg', 'min_age' => 18, 'max_age' => '', 'min_weight' => 80, 'max_weight' => '',
        ]))->assertSessionHasNoErrors();

        $max = $event->categories()->where('name', 'Sub-18')->firstOrFail();
        $this->assertNull($max->min_age);
        $this->assertSame(17, $max->max_age);
        $this->assertSame(60.0, $max->max_weight);
        $min = $event->categories()->where('name', '+80 kg')->firstOrFail();
        $this->assertSame(18, $min->min_age);
        $this->assertNull($min->max_age);
        $this->assertSame(80.0, $min->min_weight);
    }

    public function test_fourth_place_is_ignored_with_shared_bronze(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload(['bronze_rule' => 'shared_bronze', 'show_fourth' => '1']));
        $this->assertFalse($event->categories()->first()->show_fourth);
    }

    public function test_category_of_another_event_is_not_found_through_my_event(): void
    {
        $user = User::factory()->create();
        $mine = Event::factory()->for($user)->create();
        $foreign = Category::factory()->create();

        $this->actingAs($user)->get(route('panel.categories.edit', [$mine, $foreign]))->assertNotFound();
    }

    public function test_mismatches_use_age_on_event_date(): void
    {
        $event = Event::factory()->create(['starts_on' => '2026-06-15']);
        $category = Category::factory()->for($event)->create(['min_age' => 15, 'max_age' => 17, 'sex' => 'male', 'min_weight' => 55, 'max_weight' => 60]);

        $ok = Competitor::factory()->for($event)->create(['birth_date' => '2011-06-15', 'sex' => 'male', 'weight' => 58]); // 15 exactos
        $young = Competitor::factory()->for($event)->create(['birth_date' => '2011-06-16', 'sex' => 'male', 'weight' => 58]); // 14
        $heavy = Competitor::factory()->for($event)->create(['birth_date' => '2010-01-01', 'sex' => 'female', 'weight' => 70]);

        $this->assertSame([], $category->mismatchesFor($ok));
        $this->assertCount(1, $category->mismatchesFor($young));
        $this->assertCount(2, $category->mismatchesFor($heavy)); // sexo y peso
    }

    public function test_bronze_rule_cannot_change_once_results_exist(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        app(ResultRecorder::class)->record($semi, $semi->slot_a_id);
        $event = $category->event;

        $this->actingAs($event->user)->put(route('panel.categories.update', [$event, $category]), $this->payload(['name' => $category->name, 'bronze_rule' => 'shared_bronze']))
            ->assertSessionHasErrors('bronze_rule');
    }

    public function test_create_form_preloads_the_event_default_bronze_rule_and_edit_keeps_the_saved_one(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['default_bronze_rule' => 'shared_bronze']);

        $html = $this->actingAs($user)->get(route('panel.categories.create', $event))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="shared_bronze" selected/', $html);

        $category = Category::factory()->for($event)->create(['bronze_rule' => BronzeRule::BronzeMatch]);
        $html = $this->actingAs($user)->get(route('panel.categories.edit', [$event, $category]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="bronze_match" selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="shared_bronze" selected/', $html);
    }

    public function test_create_form_defaults_to_bronze_match_for_events_without_the_rule(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->get(route('panel.categories.create', $event))->assertOk()
            ->assertSee('<option value="bronze_match" selected', false);
    }
}
