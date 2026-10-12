<?php

namespace Tests\Feature;

use App\Enums\ClubStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_sets_and_resets_its_color(): void
    {
        $event = Event::factory()->registrationOpen()->create();
        $club = $this->clubFor($event);

        $this->actingAs($club)->put(route('panel.profile.color'), ['club_color' => '#AA3300'])->assertSessionHasNoErrors();
        $this->assertSame('#aa3300', $club->fresh()->club_color);

        $this->put(route('panel.profile.color'), ['club_color' => 'rojo'])->assertSessionHasErrors('club_color');

        $this->put(route('panel.profile.color'), ['club_color' => '#aa3300', 'auto_color' => 1]);
        $this->assertNull($club->fresh()->club_color);

        $this->actingAs(User::factory()->create())->put(route('panel.profile.color'), ['club_color' => '#aa3300'])->assertForbidden();
    }

    public function test_clubs_get_distinct_automatic_colors_and_explicit_colors_are_respected(): void
    {
        $event = Event::factory()->registrationOpen()->create();
        $a = $this->clubFor($event, name: 'Club A');
        $b = $this->clubFor($event, name: 'Club B');
        $c = $this->clubFor($event, name: 'Club C');
        $pending = $this->clubFor($event, ClubStatus::Pending, 'Club D');
        $b->forceFill(['club_color' => User::CLUB_PALETTE[0]])->save();

        $colors = array_map(fn (User $u) => $event->fresh()->clubColor($u->name), [$a, $b, $c]);

        $this->assertSame(User::CLUB_PALETTE[0], $colors[1]);
        $this->assertCount(3, array_unique($colors));
        $this->assertNull($event->fresh()->clubColor($pending->name));
        $this->assertNull($event->fresh()->clubColor('Club desconocido'));
    }
}
