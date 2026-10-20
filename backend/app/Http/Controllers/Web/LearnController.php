<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $lessons = Content::publiclyVisible()
            ->where(fn ($query) => $query->where('language', app()->getLocale())->orWhereNull('language'))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('learn.index', compact('lessons', 'search'));
    }

    public function show(string $slug): View
    {
        $lesson = Content::publiclyVisible()->where('slug', $slug)->firstOrFail();

        return view('learn.show', compact('lesson'));
    }
}
