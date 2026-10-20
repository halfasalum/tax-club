<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Support\AskTra;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public Ask TRA knowledge base, indexable by search engines (COM-REQ04).
 */
class AnswersController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('answers.index', [
            'answers' => AskTra::knowledgeBase($search, language: app()->getLocale(), perPage: 20),
            'search' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        return view('answers.show', [
            'question' => Question::published()->where('slug', $slug)->with('category')->firstOrFail(),
        ]);
    }
}
