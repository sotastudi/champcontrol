<?php

namespace App\Http\Middleware;

use App\Support\ResultsAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los resultados públicos de un evento exigen el código de acceso (salvo para su propietario o sus controladores de tatami). */
class EnsureResultsAccess
{
    public function __construct(private readonly ResultsAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $this->access->allows($request, $event)) {
            return redirect()->route('public.events.show', $event);
        }

        return $next($request);
    }
}
