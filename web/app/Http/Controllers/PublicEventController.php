<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Tournament\PodiumCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicEventController extends Controller
{
    public function show(Request $request, Event $event, PodiumCalculator $podium): View
    {
        abort_unless($event->isListed(), 404);

        // El propietario ve también las categorías sin publicar (con enlace al panel); el resto, solo las publicadas.
        $isOwner = (bool) $request->user()?->can('manage', $event);
        $categories = $event->categories()
            ->when(! $isOwner, fn ($q) => $q->where('is_published', true))
            ->withCount([
                'bouts as total_bouts' => fn ($q) => $q->where('required_participants', 2),
                'bouts as played_bouts' => fn ($q) => $q->where('required_participants', 2)->whereNotNull('winner_id'),
            ])
            ->orderBy('name')->get();

        // Campeón solo cuando ya se han jugado todos los combates.
        $champions = [];
        foreach ($categories as $category) {
            if ($category->total_bouts > 0 && $category->played_bouts === $category->total_bouts) {
                $first = collect($podium->for($category))->firstWhere('place', 1);
                $champions[$category->id] = $first?->registration->competitor->displayName();
            }
        }

        return view('public.events.show', [
            'event' => $event,
            'categories' => $categories,
            'champions' => $champions,
            'isOwner' => $isOwner,
        ]);
    }

    public function poster(Event $event): StreamedResponse
    {
        abort_unless($event->isListed() && $event->hasPoster(), 404);

        return Storage::disk('local')->response($event->poster_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600']);
    }
}
