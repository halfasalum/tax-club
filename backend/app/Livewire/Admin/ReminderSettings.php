<?php

namespace App\Livewire\Admin;

use App\Support\Reminders;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Turn automatic reminders on or off and choose how early they go out
 * (NOT-REQ04).
 */
#[Layout('components.layouts.app')]
class ReminderSettings extends Component
{
    /** @var array<string, array{enabled: bool, lead: int, unit: string}> */
    public array $settings = [];

    public bool $saved = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('manage_reminders'), 403);
        $this->settings = Reminders::settings();
    }

    public function save(): void
    {
        $this->validate([
            'settings.event.lead' => 'required|integer|min:1|max:168',
            'settings.opportunity.lead' => 'required|integer|min:1|max:30',
            'settings.quiz.lead' => 'required|integer|min:1|max:168',
        ]);

        foreach ($this->settings as $kind => $setting) {
            Reminders::configure($kind, (bool) $setting['enabled'], (int) $setting['lead'], auth()->user());
        }

        $this->settings = Reminders::settings();
        $this->saved = true;
    }

    public function render(): mixed
    {
        return view('livewire.admin.reminder-settings', [
            'sentThisWeek' => DB::table('reminders_sent')->where('created_at', '>=', now()->subWeek())->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind'),
        ])->title(__('Automatic reminders'));
    }
}
