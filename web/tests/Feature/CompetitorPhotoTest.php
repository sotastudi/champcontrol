<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompetitorPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function data(Category $category, array $o = []): array
    {
        return array_merge([
            'first_name' => 'Ana', 'last_name' => 'Ruiz Sala', 'birth_date' => '1995-03-04', 'sex' => 'female', 'level' => '1º dan', 'club' => 'Dojo Test',
            'category_id' => $category->id, 'status' => 'confirmed',
        ], $o);
    }

    private function setUpEvent(): array
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['starts_on' => '2026-11-20']);

        return [$user, $event, Category::factory()->for($event)->create()];
    }

    public function test_photo_is_optional_and_stored_privately_when_organizer_registers(): void
    {
        [$user, $event, $category] = $this->setUpEvent();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category))->assertSessionHasNoErrors();
        $this->assertNull(Competitor::firstOrFail()->photo_path);

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['first_name' => 'Eva', 'photo' => UploadedFile::fake()->image('f.jpg', 300, 400)]))
            ->assertSessionHasNoErrors();
        $competitor = Competitor::where('first_name', 'Eva')->firstOrFail();
        Storage::disk('local')->assertExists($competitor->photo_path);

        $this->actingAs($user)->get(route('panel.competitors.photo', [$event, $competitor]))->assertOk();
        $registration = $competitor->registrations()->firstOrFail();
        $this->actingAs($user)->get(route('panel.registrations.show', [$event, $registration]))
            ->assertSee(route('panel.competitors.photo', [$event, $competitor]), false);
    }

    public function test_photo_must_be_a_small_raster_image(): void
    {
        [$user, $event, $category] = $this->setUpEvent();

        foreach ([
            UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'),
            UploadedFile::fake()->image('big.jpg')->size(4000),
        ] as $file) {
            $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['photo' => $file]))->assertSessionHasErrors('photo');
        }
        $this->assertSame(0, Competitor::count());
    }

    public function test_photo_can_be_replaced_and_removed_on_edit(): void
    {
        [$user, $event, $category] = $this->setUpEvent();
        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['photo' => UploadedFile::fake()->image('a.jpg')]));
        $competitor = Competitor::firstOrFail();
        $first = $competitor->photo_path;
        $payload = ['first_name' => 'Ana', 'last_name' => 'Ruiz Sala', 'birth_date' => '1995-03-04', 'sex' => 'female', 'level' => '1º dan', 'club' => 'Dojo Test'];

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), $payload + ['photo' => UploadedFile::fake()->image('b.png')])->assertSessionHasNoErrors();
        $second = $competitor->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), $payload)->assertSessionHasNoErrors();
        $this->assertSame($second, $competitor->fresh()->photo_path);

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), $payload + ['remove_photo' => '1']);
        $this->assertNull($competitor->fresh()->photo_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_public_form_accepts_a_photo_but_never_serves_it_publicly(): void
    {
        Storage::fake('local');
        $event = Event::factory()->create(['is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen, 'starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();

        $this->post(route('public.registration.store', $event), $this->data($category, ['photo' => UploadedFile::fake()->image('f.jpg')]))->assertSessionHasNoErrors();
        $competitor = Competitor::firstOrFail();
        Storage::disk('local')->assertExists($competitor->photo_path);

        $this->get(route('public.events.show', $event))->assertDontSee($competitor->photo_path);
        $this->get(route('panel.competitors.photo', [$event, $competitor]))->assertRedirect(route('login'));
    }

    public function test_photo_of_another_organizer_is_forbidden_and_deleting_the_event_removes_files(): void
    {
        [$user, $event, $category] = $this->setUpEvent();
        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data($category, ['photo' => UploadedFile::fake()->image('a.jpg')]));
        $competitor = Competitor::firstOrFail();
        $path = $competitor->photo_path;

        $this->actingAs(User::factory()->create())->get(route('panel.competitors.photo', [$event, $competitor]))->assertForbidden();

        $this->actingAs($user)->delete(route('panel.events.destroy', $event));
        Storage::disk('local')->assertMissing($path);
    }
}
