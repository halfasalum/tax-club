<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Event;
use App\Models\Institution;
use App\Models\User;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home', [
            'lessons' => Content::publiclyVisible()->latest('published_at')->limit(3)->get(),
            'events' => Event::upcoming()->orderBy('starts_at')->limit(3)->get(),
            'memberCount' => User::where('account_status', User::STATUS_ACTIVE)->count(),
            'institutionCount' => Institution::count(),
        ]);
    }

    public function about(): View
    {
        return view('pages.about');
    }
}
