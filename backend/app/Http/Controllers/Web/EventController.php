<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::publiclyListed()->orderBy('starts_at')->paginate(12);

        return view('events.index', compact('events'));
    }

    public function show(string $slug): View
    {
        $event = Event::live()->where('is_public', true)->where('slug', $slug)->firstOrFail();

        return view('events.show', compact('event'));
    }
}
