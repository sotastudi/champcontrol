<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TatamiBoardTest extends TestCase
{
    use RefreshDatabase;

    private function category(Event $event, string $name, ?int $tatami, int $n): Category
    {
        $category = Category::factory()->for($event)->create(['name' => $name, 'tatami' => $tatami]);
        Registration::factory()->count($n)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return $category;
    }

    public function test_each_tatami_block_shows_its_categories_and_the_current_and_next_bouts(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['tatami_count' => 3]);
        $a = $this->category($event, 'Cat Uno', 1, 4);
        $b = $this->category($event, 'Cat Dos', 2, 2);
        $this->category($event, 'Cat Sin Tatami', null, 2);

        $html = $this->actingAs($user)->get(route('panel.events.show', $event))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'tatami-block'));
        $this->assertStringContainsString('Tatami 1', $html);
        $this->assertStringContainsString('Tatami 3', $html);
        $this->assertStringContainsString('Sin categorías asignadas', $html, 'el tatami 3 está vacío');

        // Tatami 1: combate 1 de «Cat Uno» ahora y el combate 2 a continuación.
        $first = $a->bouts()->where('number', 1)->with(['slotA.competitor', 'slotB.competitor'])->first();
        $this->assertStringContainsString('Cat Uno · Combate 1', $html);
        $this->assertStringContainsString($first->slotA->competitor->displayName(), $html);
        $this->assertStringContainsString('A continuación', $html);
        $this->assertStringContainsString('Cat Uno · Combate 2:', $html);
        $this->assertStringNotContainsString('Cat Sin Tatami · Combate', $html, 'sin tatami no entra en ningún bloque');
        $this->assertStringContainsString('Cat Dos · Combate 1', $html);
    }

    public function test_the_current_bout_advances_when_a_result_is_recorded(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['tatami_count' => 1]);
        $a = $this->category($event, 'Cat Uno', 1, 4);

        $recorder = app(ResultRecorder::class);
        $first = $a->bouts()->where('number', 1)->first();
        $recorder->record($first, $first->slot_a_id);
        $second = $a->bouts()->where('number', 2)->first();
        $recorder->record($second, $second->slot_a_id);

        // Quedan la final (3) y el bronce (4): la final es la actual.
        $html = $this->actingAs($user)->get(route('panel.events.show', $event))->getContent();
        $this->assertStringContainsString('Cat Uno · Combate 3', $html);
        $this->assertMatchesRegularExpression('#Ahora.*?Cat Uno · Combate 3#s', $html);
        $this->assertStringNotContainsString('Cat Uno · Combate 1<', $html);
    }

    public function test_a_finished_tatami_and_other_organizers_are_handled(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['tatami_count' => 1]);
        Category::factory()->for($event)->create(['name' => 'Cat Vacía', 'tatami' => 1]);

        $this->actingAs($user)->get(route('panel.events.show', $event))->assertSee('Sin combates listos');
        $this->actingAs(User::factory()->create())->get(route('panel.events.show', $event))->assertForbidden();
    }
}
