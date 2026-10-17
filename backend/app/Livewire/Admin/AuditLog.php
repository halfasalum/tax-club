<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog as AuditRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only view of the audit trail (NFR-12).
 */
#[Layout('components.layouts.app')]
class AuditLog extends Component
{
    use WithPagination;

    /** Action prefixes grouped for the filter. */
    public const AREAS = [
        'auth' => ['auth.'],
        'members' => ['user.', 'member_status_change.'],
        'institutions' => ['institution.', 'tax_club.', 'club_patron.', 'coordinator_assignment.'],
        'notifications' => ['notification_template.', 'sms.'],
    ];

    #[Url]
    public string $area = '';

    #[Url]
    public string $actor = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $subject = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('area', 'actor', 'from', 'to', 'subject');
        $this->resetPage();
    }

    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $actor = trim($this->actor);

        return AuditRecord::query()
            ->with('actor:user_id,full_name,phone')
            ->when(isset(self::AREAS[$this->area]), fn ($query) => $query->where(function ($query) {
                foreach (self::AREAS[$this->area] as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'%');
                }
            }))
            ->when($actor !== '', fn ($query) => $query->whereHas('actor', fn ($query) => $query
                ->where('full_name', 'like', "%{$actor}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $actor).'%')))
            ->when($this->subject !== '', fn ($query) => $query->where('auditable_id', $this->subject))
            ->when($this->from !== '', fn ($query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($query) => $query->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id')
            ->paginate(30);
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'auth.login' => __('Signed in'),
            'auth.login_failed' => __('Failed sign-in'),
            'auth.logout' => __('Signed out'),
            'auth.two_factor_failed' => __('Wrong sign-in code'),
            'auth.two_factor_skipped' => __('Sign-in code skipped (no phone)'),
            'auth.password_reset_requested' => __('Password reset requested'),
            'auth.password_reset' => __('Password reset'),
            default => __(str_replace(['_', '.'], ' ', ucfirst($action))),
        };
    }

    public function render(): mixed
    {
        return view('livewire.admin.audit-log', [
            'hasFilters' => $this->area.$this->actor.$this->from.$this->to.$this->subject !== '',
        ])->title(__('Audit log'));
    }
}
