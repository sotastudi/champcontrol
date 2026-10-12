<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $o = []): array
    {
        return array_merge([
            'first_name' => 'José', 'last_name' => 'García Pérez', 'club' => 'Dojo Norte',
            'birth_date' => '2010-06-15', 'sex' => 'male', 'level' => '4º kyu', 'weight' => 58.5,
        ], $o);
    }

    public function test_organizer_registers_a_competitor_manually(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data([
            'category_id' => $category->id, 'status' => 'confirmed',
        ]))->assertRedirect(route('panel.registrations.index', $event));

        $registration = Registration::firstOrFail();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame($event->id, $registration->competitor->event_id);
    }

    public function test_cannot_register_into_a_category_of_another_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $foreign = Category::factory()->create();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data(['category_id' => $foreign->id, 'status' => 'pending']))
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_duplicate_registration_in_same_category_is_rejected(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $service->register($category, $this->data());

        $this->expectException(RegistrationException::class);
        $service->register($category, $this->data());
    }

    /** Review Focus 1: misma persona con otra capitalización, acentos y espacios. */
    public function test_duplicate_detection_ignores_case_accents_and_extra_spaces(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $service->register($category, $this->data());

        try {
            $service->register($category, $this->data(['first_name' => '  jose ', 'last_name' => 'GARCIA   PEREZ']));
            $this->fail('Debió detectar el duplicado');
        } catch (RegistrationException) {
            $this->assertDatabaseCount('competitors', 1);
            $this->assertDatabaseCount('registrations', 1);
        }
    }

    public function test_same_competitor_can_join_two_categories_and_reuses_the_competitor(): void
    {
        $event = Event::factory()->create();
        $a = Category::factory()->for($event)->create();
        $b = Category::factory()->for($event)->create();
        $service = app(RegistrationService::class);

        $service->register($a, $this->data());
        $service->register($b, $this->data());

        $this->assertDatabaseCount('competitors', 1);
        $this->assertDatabaseCount('registrations', 2);
    }

    public function test_cancelled_registration_is_reactivated_instead_of_duplicated(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $registration = $service->register($category, $this->data(), RegistrationStatus::Confirmed);
        $service->changeStatus($registration, RegistrationStatus::Cancelled);

        $again = $service->register($category, $this->data());

        $this->assertSame($registration->id, $again->id);
        $this->assertSame(RegistrationStatus::Pending, $again->fresh()->status);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_same_name_and_birthdate_in_another_event_is_a_different_competitor(): void
    {
        $service = app(RegistrationService::class);
        $service->register(Category::factory()->create(), $this->data());
        $service->register(Category::factory()->create(), $this->data());

        $this->assertDatabaseCount('competitors', 2);
    }

    public function test_status_change_and_filters(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create(['name' => 'A']);
        $other = Category::factory()->for($event)->create(['name' => 'B']);
        $r1 = Registration::factory()->forCategory($category)->create();
        Registration::factory()->forCategory($other)->confirmed()->create();

        $this->actingAs($user)->patch(route('panel.registrations.update', [$event, $r1]), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame(RegistrationStatus::Confirmed, $r1->fresh()->status);

        $this->actingAs($user)->patch(route('panel.registrations.update', [$event, $r1]), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $page = $this->actingAs($user)->get(route('panel.registrations.index', ['event' => $event, 'category_id' => $other->id]));
        $page->assertOk()->assertSee($other->registrations()->first()->competitor->last_name)
            ->assertDontSee($r1->competitor->last_name);
    }

    public function test_competitor_data_can_be_edited_by_owner(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $competitor = Competitor::factory()->for($event)->create();

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), $this->data(['club' => 'Nuevo Club']))
            ->assertRedirect(route('panel.registrations.index', $event));
        $this->assertSame('Nuevo Club', $competitor->fresh()->club);
    }

    public function test_forms_and_event_summary_render(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create();
        $registration = Registration::factory()->forCategory($category)->confirmed()->create();

        $this->actingAs($user)->get(route('panel.registrations.create', $event))->assertOk();
        $this->actingAs($user)->get(route('panel.competitors.edit', [$event, $registration->competitor]))
            ->assertOk()->assertSee($registration->competitor->last_name);
        $this->actingAs($user)->get(route('panel.events.show', $event))->assertOk()->assertSee('Inscripciones');
        $this->assertSame(1, $event->categories()->withCount(['registrations as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')])->first()->confirmed_count);
    }

    public function test_editing_to_a_name_variant_of_another_competitor_is_rejected(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        Competitor::factory()->for($event)->create(['first_name' => 'José', 'last_name' => 'García Pérez', 'birth_date' => '2010-06-15']);
        $b = Competitor::factory()->for($event)->create(['birth_date' => '2010-06-15']);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $b]), $this->data(['first_name' => ' JOSE', 'last_name' => 'garcia   perez']))
            ->assertSessionHasErrors('last_name');
    }

    public function test_editing_without_changing_identity_or_with_other_birth_date_is_allowed(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $a = Competitor::factory()->for($event)->create(['first_name' => 'José', 'last_name' => 'García Pérez', 'birth_date' => '2010-06-15']);
        $b = Competitor::factory()->for($event)->create(['birth_date' => '2011-01-01']);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $a]), $this->data(['club' => 'Otro']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Otro', $a->fresh()->club);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $b]), $this->data(['birth_date' => '2011-02-02']))
            ->assertSessionHasNoErrors();
    }

    public function test_organizer_sees_the_registration_card_before_confirming(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();
        $competitor = Competitor::factory()->for($event)->create([
            'first_name' => 'Lucía', 'last_name' => 'Mora Díez', 'birth_date' => '2012-05-05', 'club' => 'Dojo Sur',
            'phone' => '600111222', 'guardian_name' => 'Marta Díez', 'guardian_contact' => 'marta@example.com', 'notes' => 'Alergia al látex',
        ]);
        $registration = Registration::factory()->forCategory($category)->create(['competitor_id' => $competitor->id, 'status' => RegistrationStatus::Pending]);

        $this->actingAs($user)->get(route('panel.registrations.index', $event))
            ->assertSee(route('panel.registrations.show', [$event, $registration]), false);

        $this->actingAs($user)->get(route('panel.registrations.show', [$event, $registration]))
            ->assertOk()
            ->assertSee('Mora Díez, Lucía')->assertSee('05/05/2012')->assertSee('14 años')
            ->assertSee('Dojo Sur')->assertSee('600111222')->assertSee('Marta Díez')->assertSee('marta@example.com')
            ->assertSee('Alergia al látex')->assertSee('Confirmar');
    }

    public function test_registration_card_of_another_event_is_not_found_or_forbidden(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $otherEvent = Event::factory()->for($user)->create();
        $registration = Registration::factory()->forCategory(Category::factory()->for($otherEvent)->create())->create();

        $this->actingAs($user)->get(route('panel.registrations.show', [$event, $registration]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('panel.registrations.show', [$otherEvent, $registration]))->assertForbidden();
    }

    public function test_sex_and_level_are_required_and_marked_with_an_asterisk(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();

        $html = $this->actingAs($user)->get(route('panel.registrations.create', $event))->getContent();
        $this->assertMatchesRegularExpression('/for="sex"[^>]*>Sexo[^<]*<span class="text-danger">\*/', $html);
        $this->assertMatchesRegularExpression('/for="level"[^>]*>Nivel[^<]*<span class="text-danger">\*/', $html);

        foreach (['sex', 'level'] as $field) {
            $this->actingAs($user)->post(route('panel.registrations.store', $event), array_diff_key(
                $this->data(['category_id' => $category->id, 'status' => 'confirmed']), [$field => 1]
            ))->assertSessionHasErrors($field);
        }
        $this->assertSame(0, Registration::count());
    }

    public function test_club_is_required_in_every_competitor_form_and_marked_with_an_asterisk(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['starts_on' => '2026-11-20', 'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen]);
        $category = Category::factory()->for($event)->create();
        $payload = $this->data(['category_id' => $category->id, 'status' => 'confirmed']);

        $html = $this->actingAs($user)->get(route('panel.registrations.create', $event))->getContent();
        $this->assertMatchesRegularExpression('/for="club"[^>]*>Club[^<]*<span class="text-danger">\*/', $html);

        foreach (['', '   '] as $empty) {
            $this->actingAs($user)->post(route('panel.registrations.store', $event), ['club' => $empty] + $payload)->assertSessionHasErrors('club');
        }
        $this->actingAs($user)->post(route('panel.registrations.store', $event), array_diff_key($payload, ['club' => 1]))->assertSessionHasErrors('club');
        $this->assertSame(0, Registration::count());

        $competitor = Competitor::factory()->for($event)->create();
        $edit = array_diff_key($payload, ['category_id' => 1, 'status' => 1]);
        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), ['club' => ''] + $edit)->assertSessionHasErrors('club');
        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), ['club' => 'Dojo Norte'] + $edit)->assertSessionHasNoErrors();

        // En el formulario público el club es siempre el de la cuenta del club aprobada (ver ClubRegistrationTest).
        auth()->logout();
        $this->post(route('public.registration.store', $event), ['club' => ''] + $payload)->assertRedirect(route('login'));
    }

    public function test_registration_action_buttons_have_their_colours_and_icons_in_the_list_and_in_the_card(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create();
        $registration = Registration::factory()->forCategory($category)->create(['status' => RegistrationStatus::Pending]);

        $button = fn (string $class, string $icon, string $text, string $size) => '#class="btn '.$class.$size.' btn-icon btn-compact" title="'.$text.'" aria-label="'.$text.'"[^>]*><svg[^>]*><use href="'.preg_quote('#'.$icon, '#').'"/></svg> <span class="btn-label">'.$text.'</span>#';

        $list = $this->actingAs($user)->get(route('panel.registrations.index', $event))->getContent();
        $this->assertMatchesRegularExpression($button('btn-outline-success', 'check', 'Confirmar', ' btn-sm'), $list);
        $this->assertMatchesRegularExpression($button('btn-outline-secondary', 'ban', 'Cancelar', ' btn-sm'), $list);
        $this->assertMatchesRegularExpression($button('btn-outline-info', 'eye', 'Ver ficha', ' btn-sm'), $list);
        $this->assertMatchesRegularExpression($button('btn-yellow', 'pencil', 'Editar', ' btn-sm'), $list);
        $this->assertStringNotContainsString('>Pendiente</button>', $list, 'el estado actual no ofrece su propio botón');

        // Ya confirmada: aparece Pendiente en naranja con reloj y desaparece Confirmar.
        $registration->update(['status' => RegistrationStatus::Confirmed]);
        $confirmed = $this->actingAs($user)->get(route('panel.registrations.index', $event))->getContent();
        $this->assertMatchesRegularExpression($button('btn-orange', 'clock', 'Pendiente', ' btn-sm'), $confirmed);
        $this->assertDoesNotMatchRegularExpression($button('btn-outline-success', 'check', 'Confirmar', ' btn-sm'), $confirmed);

        $card = $this->actingAs($user)->get(route('panel.registrations.show', [$event, $registration]))->getContent();
        $this->assertMatchesRegularExpression($button('btn-orange', 'clock', 'Pendiente', ''), $card);
        $this->assertMatchesRegularExpression($button('btn-outline-secondary', 'ban', 'Cancelar', ''), $card);
        $this->assertMatchesRegularExpression($button('btn-yellow', 'pencil', 'Editar', ''), $card);
        foreach (['check', 'ban', 'clock', 'eye', 'pencil'] as $icon) {
            $this->assertStringContainsString('<symbol id="'.$icon.'"', $card);
        }
    }

    public function test_print_buttons_have_a_printer_icon(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create();
        $registration = Registration::factory()->forCategory($category)->create();

        foreach ([route('panel.registrations.index', $event), route('panel.registrations.show', [$event, $registration])] as $url) {
            $html = $this->actingAs($user)->get($url)->getContent();
            $this->assertMatchesRegularExpression('#class="btn btn-outline-secondary btn-icon" onclick="window.print\(\)"><svg[^>]*><use href="\#printer"/></svg> Imprimir</button>#', $html, $url);
            $this->assertStringContainsString('<symbol id="printer"', $html);
        }
    }

    public function test_confirm_all_confirms_only_pending_registrations_matching_the_current_filter(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $a = Category::factory()->for($event)->create(['name' => 'Categoría A']);
        $b = Category::factory()->for($event)->create(['name' => 'Categoría B']);
        $pendingA1 = Registration::factory()->forCategory($a)->create(['status' => 'pending']);
        $pendingA2 = Registration::factory()->forCategory($a)->create(['status' => 'pending']);
        $cancelledA = Registration::factory()->forCategory($a)->create(['status' => 'cancelled']);
        $pendingB = Registration::factory()->forCategory($b)->create(['status' => 'pending']);

        $this->actingAs($user)->get(route('panel.registrations.index', ['event' => $event, 'category_id' => $a->id]))
            ->assertSee('Confirmar todos (2 pendientes con el filtro actual)');

        $this->post(route('panel.registrations.confirm-all', $event), ['category_id' => $a->id])
            ->assertRedirect(route('panel.registrations.index', ['event' => $event, 'category_id' => $a->id]));

        $this->assertSame('confirmed', $pendingA1->fresh()->status->value);
        $this->assertSame('confirmed', $pendingA2->fresh()->status->value);
        $this->assertSame('cancelled', $cancelledA->fresh()->status->value);
        $this->assertSame('pending', $pendingB->fresh()->status->value);

        // Sin filtro confirma el resto de pendientes; con nada pendiente el botón desaparece.
        $this->post(route('panel.registrations.confirm-all', $event));
        $this->assertSame('confirmed', $pendingB->fresh()->status->value);
        $this->get(route('panel.registrations.index', $event))->assertDontSee('Confirmar todos');
    }

    public function test_confirm_all_is_limited_to_the_own_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();
        $foreign = Registration::factory()->forCategory(Category::factory()->for(Event::factory()->create())->create())->create(['status' => 'pending']);

        $this->actingAs(User::factory()->create())->post(route('panel.registrations.confirm-all', $event))->assertForbidden();
        $this->actingAs($owner)->post(route('panel.registrations.confirm-all', $event));
        $this->assertSame('pending', $foreign->fresh()->status->value);
    }
}
