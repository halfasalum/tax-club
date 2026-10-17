<?php

namespace App\Livewire\Admin;

use App\Models\AppSetting;
use App\Models\Badge;
use App\Support\Audit;
use App\Support\Gamification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Point values, daily cap, leaderboards and badges (GAM-REQ08-10).
 */
#[Layout('components.layouts.app')]
class GamificationSettings extends Component
{
    /** @var array<string, int> */
    public array $rules = [];

    public int $dailyCap = Gamification::DEFAULT_DAILY_CAP;

    public bool $leaderboardsEnabled = true;

    public ?int $editingBadge = null;

    /** @var array<string, mixed> */
    public array $badge = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        foreach (array_keys(Gamification::DEFAULT_RULES) as $key) {
            $this->rules[$key] = Gamification::rule($key);
        }

        $this->dailyCap = Gamification::dailyCap();
        $this->leaderboardsEnabled = (bool) AppSetting::get('leaderboards.enabled', true);
    }

    public function saveRules(): void
    {
        $this->validate([
            'rules.*' => 'required|integer|min:0|max:1000',
            'dailyCap' => 'required|integer|min:1|max:10000',
        ]);

        $before = ['rules' => collect(Gamification::DEFAULT_RULES)->keys()->mapWithKeys(fn ($key) => [$key => Gamification::rule($key)])->all(), 'daily_cap' => Gamification::dailyCap()];

        foreach ($this->rules as $key => $points) {
            DB::table('gamification_rules')->updateOrInsert(['key' => $key], ['points' => (int) $points, 'is_active' => true, 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        }

        Once::flush();
        AppSetting::put('gamification.daily_cap', $this->dailyCap, auth()->user());
        AppSetting::put('leaderboards.enabled', $this->leaderboardsEnabled, auth()->user());
        Audit::record('gamification.rules_changed', before: $before, after: ['rules' => $this->rules, 'daily_cap' => $this->dailyCap, 'leaderboards' => $this->leaderboardsEnabled]);

        $this->statusMessage = __('Points rules saved. They apply from now on; points already earned do not change.');
    }

    public function editBadge(int $id): void
    {
        $this->editingBadge = $id;
        $this->badge = Badge::findOrFail($id)->only(['icon', 'name_sw', 'name_en', 'description_sw', 'description_en', 'threshold', 'is_active']);
    }

    public function saveBadge(): void
    {
        $this->validate([
            'badge.icon' => 'required|string|max:16',
            'badge.name_sw' => 'required|string|max:120',
            'badge.name_en' => 'required|string|max:120',
            'badge.description_sw' => 'nullable|string|max:255',
            'badge.description_en' => 'nullable|string|max:255',
            'badge.threshold' => 'required|integer|min:1|max:100000',
        ]);

        Badge::findOrFail($this->editingBadge)->update([...$this->badge, 'is_active' => (bool) ($this->badge['is_active'] ?? false)]);
        $this->reset('editingBadge', 'badge');
        $this->statusMessage = __('Badge saved.');
    }

    public function render(): mixed
    {
        return view('livewire.admin.gamification-settings', [
            'badges' => Badge::withCount(['earners' => fn ($query) => $query])->orderBy('sort_order')->get(),
        ])->title(__('Points and badges'));
    }
}
