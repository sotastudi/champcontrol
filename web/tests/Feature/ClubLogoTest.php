<?php

namespace Tests\Feature;

use App\Enums\ClubStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventClub;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClubLogoTest extends TestCase
{
    use RefreshDatabase;

    private function clubWithLogo(Event $event, ClubStatus $status = ClubStatus::Approved): User
    {
        $club = $this->clubFor($event, $status);
        $this->actingAs($club)->put(route('panel.profile.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHasNoErrors();

        return $club->fresh();
    }

    public function test_club_uploads_replaces_and_removes_its_logo(): void
    {
        Storage::fake('local');
        $event = Event::factory()->registrationOpen()->create();
        $club = $this->clubWithLogo($event);
        $first = $club->logo_path;
        Storage::disk('local')->assertExists($first);
        $this->get(route('panel.profile.logo.show'))->assertOk();

        $this->put(route('panel.profile.logo'), ['logo' => UploadedFile::fake()->image('otro.jpg')]);
        Storage::disk('local')->assertMissing($first);

        $this->put(route('panel.profile.logo'), ['remove_logo' => '1']);
        $this->assertNull($club->fresh()->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('club-logos'));
    }

    public function test_logo_validation_and_account_type(): void
    {
        Storage::fake('local');
        $event = Event::factory()->registrationOpen()->create();
        $this->actingAs($this->clubFor($event));

        $this->put(route('panel.profile.logo'), ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->put(route('panel.profile.logo'), ['logo' => UploadedFile::fake()->image('grande.png')->size(3000)])->assertSessionHasErrors('logo');

        $this->actingAs(User::factory()->create())->put(route('panel.profile.logo'), ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
    }

    public function test_logo_is_public_only_for_approved_clubs_in_listed_events(): void
    {
        Storage::fake('local');
        $event = Event::factory()->registrationOpen()->create();
        $club = $this->clubWithLogo($event);
        $registration = $club->clubRegistrationFor($event);
        auth()->logout();

        $this->get(route('public.clubs.logo', [$event, $registration]))->assertOk();

        $registration->update(['status' => ClubStatus::Pending]);
        $this->get(route('public.clubs.logo', [$event, $registration]))->assertNotFound();

        $registration->update(['status' => ClubStatus::Approved]);
        $other = Event::factory()->registrationOpen()->create();
        $this->get(route('public.clubs.logo', [$other, $registration]))->assertNotFound();

        $event->update(['is_public' => false]);
        $this->get(route('public.clubs.logo', [$event, $registration]))->assertNotFound();
        $this->actingAs($event->user)->get(route('public.clubs.logo', [$event, $registration]))->assertOk();
    }

    public function test_tatami_views_show_the_logo_next_to_the_club_name(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->registrationOpen()->create(['tatami_count' => 1]);
        $club = $this->clubWithLogo($event);
        $url = route('public.clubs.logo', [$event, $club->clubRegistrationFor($event)]);

        $category = Category::factory()->for($event)->create(['tatami' => 1, 'tatami_position' => 1, 'is_published' => true]);
        $a = Registration::factory()->forCategory($category)->create();
        $b = Registration::factory()->forCategory($category)->create();
        $a->competitor->update(['club' => $club->name]);
        $b->competitor->update(['club' => 'Otro Club']);
        $a->update(['status' => 'confirmed']);
        $b->update(['status' => 'confirmed']);
        app(\App\Tournament\BracketGenerator::class)->generate($category);

        $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 1]))->assertSee($url, false)->assertSee('Otro Club');
        auth()->logout();
        $this->seeResults($event)->get(route('public.events.show', $event))->assertSee($url, false);
    }
}
