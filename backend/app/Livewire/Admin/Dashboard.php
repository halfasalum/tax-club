<?php

namespace App\Livewire\Admin;

use App\Models\Content;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Opportunity;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public function render(): mixed
    {
        return view('livewire.admin.dashboard', [
            'pendingRegistrations' => User::where('account_status', User::STATUS_PENDING_VERIFICATION)->count(),
            'activeMembers' => User::where('account_status', User::STATUS_ACTIVE)->count(),
            'institutions' => Institution::count(),
            'publishedContent' => Content::where('is_active', true)->count(),
            'upcomingEvents' => Event::upcoming()->count(),
            'openOpportunities' => Opportunity::open()->count(),
            'quizAttempts' => QuizAttempt::count(),
            'recentRegistrations' => User::with('institution:institution_id,name')
                ->whereNotNull('registration_reference')
                ->latest('created_at')
                ->limit(6)
                ->get(['user_id', 'full_name', 'user_type', 'institution_id', 'account_status', 'registration_reference', 'created_at']),
        ])->title(__('Administration'));
    }
}
