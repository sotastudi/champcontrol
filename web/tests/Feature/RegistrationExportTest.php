<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_downloads_csv_with_filters_and_safe_cells(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $a = Category::factory()->for($event)->create(['name' => 'Cat A']);
        $b = Category::factory()->for($event)->create(['name' => 'Cat B']);
        $competitor = Competitor::factory()->for($event)->create(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'club' => '=1+1']);
        Registration::factory()->confirmed()->create(['category_id' => $a->id, 'competitor_id' => $competitor->id]);
        Registration::factory()->forCategory($b)->create();

        $response = $this->actingAs($user)->get(route('panel.exports.registrations', ['event' => $event, 'category_id' => $a->id]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Categoría;Apellidos;Nombre', $csv);
        $this->assertStringContainsString('"Cat A";Ruiz;Ana;\'=1+1', $csv);
        $this->assertStringNotContainsString('Cat B', $csv);
    }

    public function test_other_organizers_cannot_export(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('panel.exports.registrations', $event))->assertForbidden();
    }
}
