<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', ['events' => Event::listed()->orderBy('starts_on')->get()]);
    }
}
