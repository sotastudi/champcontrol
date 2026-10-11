<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\WithdrawalType;
use App\Models\Category;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\ResultRecorder;
use App\Tournament\WithdrawalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class WithdrawalPanelTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    /** n=4 con la semifinal 1 jugada: hay resultados reales. */
    private function started(): Category
    {
        $category = $this->generated(4);
        $category->event->update(['is_public' => true, 'status' => EventStatus::InProgress]);
        $category->forceFill(['is_published' => true])->save();
        $s1 = $this->bout($category, 1, 1);
        app(ResultRecorder::class)->record($s1, $s1->slot_a_id);

        return $category->refresh();
    }

    private function store(User $user, Category $category, array $data)
    {
        return $this->actingAs($user)->post(route('panel.withdrawals.store', [$category->event, $category]), $data);
    }

    public function test_owner_registers_a_withdrawal(): void
    {
        $category = $this->started();
        $target = $this->bout($category, 1, 1)->slot_b_id;

        $this->store($category->event->user, $category, ['registration_id' => $target, 'type' => 'withdrawn', 'reason' => 'lesión'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(WithdrawalType::Withdrawn, Registration::find($target)->withdrawal_type);
    }

    public function test_tournament_errors_are_shown_as_form_errors(): void
    {
        $category = $this->generated(4);   // sin resultados
        $category->event->update(['status' => EventStatus::InProgress]);
        $target = $this->bout($category, 1, 1)->slot_a_id;

        $this->store($category->event->user, $category, ['registration_id' => $target, 'type' => 'disqualified'])
            ->assertSessionHasErrors('error');
    }

    public function test_intruder_is_forbidden(): void
    {
        $category = $this->started();

        $this->store(User::factory()->create(), $category, ['registration_id' => $this->bout($category, 1, 1)->slot_b_id, 'type' => 'withdrawn'])
            ->assertForbidden();
    }

    public function test_registration_of_another_category_is_404(): void
    {
        $category = $this->started();
        $other = Registration::factory()->confirmed()->forCategory($this->generated(2))->create();

        $this->store($category->event->user, $category, ['registration_id' => $other->id, 'type' => 'withdrawn'])->assertNotFound();
    }

    public function test_invalid_type_is_rejected(): void
    {
        $category = $this->started();
        $target = $this->bout($category, 1, 1)->slot_b_id;

        $this->store($category->event->user, $category, ['registration_id' => $target, 'type' => 'banished'])
            ->assertSessionHasErrors('type');
        $this->assertNull(Registration::find($target)->withdrawal_type);
    }

    public function test_panel_shows_the_section_only_with_results_and_lists_withdrawals_privately(): void
    {
        $category = $this->generated(4);
        $owner = $category->event->user;
        $this->actingAs($owner)->get(route('panel.brackets.show', [$category->event, $category]))
            ->assertOk()->assertDontSee('Retiradas y descalificaciones');

        $category = $this->started();
        $target = Registration::find($this->bout($category, 1, 1)->slot_b_id);
        app(WithdrawalRecorder::class)->record($target, WithdrawalType::Disqualified, 'motivo-secreto-xyz');

        $this->actingAs($category->event->user)->get(route('panel.brackets.show', [$category->event, $category]))
            ->assertOk()->assertSee('Retiradas y descalificaciones')->assertSee('motivo-secreto-xyz')->assertSee('(descalificado)');
    }

    public function test_panel_flags_a_vacant_place_after_a_disqualification(): void
    {
        $category = $this->started();
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        app(WithdrawalRecorder::class)->record(Registration::find($s1->slot_a_id), WithdrawalType::Disqualified);
        app(ResultRecorder::class)->record($s2, $s2->slot_a_id);

        $this->actingAs($category->event->user)->get(route('panel.brackets.show', [$category->event, $category]))
            ->assertSee('Puesto vacante (descalificación o retirada sin combatir): resolver según el reglamento.');
        $this->get(route('public.categories.show', [$category->event, $category]))
            ->assertOk()->assertDontSee('vacante');
    }

    public function test_public_page_never_reveals_withdrawals_or_reasons(): void
    {
        $category = $this->started();
        $s2 = $this->bout($category, 1, 2);
        app(WithdrawalRecorder::class)->record(Registration::find($this->bout($category, 1, 1)->slot_a_id), WithdrawalType::Disqualified, 'motivo-secreto-xyz');
        app(ResultRecorder::class)->record($s2, $s2->slot_a_id);   // la final se gana por walkover

        $response = $this->get(route('public.categories.show', [$category->event, $category]))->assertOk();

        $response->assertSee('Gana por incomparecencia')
            ->assertDontSee('motivo-secreto-xyz')
            ->assertDontSee('descalific', false)->assertDontSee('Descalific', false)
            ->assertDontSee('retirad', false)->assertDontSee('Retirad', false);
    }
}
