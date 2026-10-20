<?php

namespace App\Livewire\Admin;

use App\Models\Certificate;
use App\Models\User;
use App\Support\Certificates as CertificateService;
use App\Support\MembershipCard;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Issued certificates: search, issue by hand, revoke (CER-REQ01/02).
 */
#[Layout('components.layouts.app')]
class Certificates extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $number = '';

    public string $titleSw = 'Cheti cha Pongezi';

    public string $titleEn = 'Certificate of Recognition';

    public string $subject = '';

    public ?string $revoking = null;

    public string $reason = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('issue_certificates'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function issue(): void
    {
        $this->validate(['number' => 'required|string', 'titleSw' => 'required|string|max:200', 'titleEn' => 'required|string|max:200', 'subject' => 'required|string|max:240']);
        $member = User::where('membership_id', MembershipCard::normalizeNumber($this->number))->first();

        if (! $member) {
            $this->addError('number', __('No member has this number.'));

            return;
        }

        $certificate = CertificateService::issue($member, 'other', $this->titleSw, $this->titleEn, $this->subject, by: auth()->user());
        $this->reset('number', 'subject');
        $this->statusMessage = __('Certificate :number issued to :name.', ['number' => $certificate->number, 'name' => $member->full_name]);
    }

    public function revoke(): void
    {
        try {
            CertificateService::revoke(Certificate::findOrFail($this->revoking), $this->reason, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reset('revoking', 'reason');
        $this->statusMessage = __('Certificate withdrawn. It now shows as not valid.');
    }

    public function render(): mixed
    {
        $search = trim($this->search);

        return view('livewire.admin.certificates.index', [
            'certificates' => Certificate::with('user:user_id,full_name,membership_id')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('number', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")->orWhereHas('user', fn ($query) => $query->where('full_name', 'like', "%{$search}%")->orWhere('membership_id', 'like', "%{$search}%"))))
                ->latest('issued_at')->paginate(25),
        ])->title(__('Certificates'));
    }
}
