<?php

namespace App\Livewire\Admin\Moderation;

use App\Support\ContentFilter;
use App\Support\SafetyDesk;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Safety and moderation figures, and the content filter settings
 * (SAF-REQ06, SAF-REQ03).
 */
#[Layout('components.layouts.app')]
class Report extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public string $blockedWords = '';

    public int $autoHideAfter = SafetyDesk::DEFAULT_AUTO_HIDE;

    public bool $saved = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('view_safety_reports') || auth()->user()->hasControl('manage_safety_settings'), 403);
        $this->from = $this->from ?: now()->subDays(90)->toDateString();
        $this->to = $this->to ?: now()->toDateString();
        $this->blockedWords = implode(', ', ContentFilter::blockedWords());
        $this->autoHideAfter = SafetyDesk::autoHideThreshold();
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()->hasControl('manage_safety_settings'), 403);
        $this->validate(['blockedWords' => 'nullable|string|max:10000', 'autoHideAfter' => 'required|integer|min:1|max:20']);
        SafetyDesk::configure(preg_split('/[,\n]+/', $this->blockedWords) ?: [], $this->autoHideAfter, auth()->user());
        $this->saved = true;
    }

    public function render(): mixed
    {
        $from = rescue(fn () => Carbon::parse($this->from)->startOfDay(), now()->subDays(90), false);
        $to = rescue(fn () => Carbon::parse($this->to)->endOfDay(), now(), false);

        return view('livewire.admin.moderation.report', [
            'stats' => SafetyDesk::statistics($from, $to),
            'canConfigure' => auth()->user()->hasControl('manage_safety_settings'),
        ])->title(__('Safety report'));
    }
}
