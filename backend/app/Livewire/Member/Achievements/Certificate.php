<?php

namespace App\Livewire\Member\Achievements;

use App\Models\Certificate as CertificateModel;
use App\Support\Certificates;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A printable certificate with its verification QR code (CER-REQ01/02).
 */
#[Layout('components.layouts.app')]
class Certificate extends Component
{
    public CertificateModel $certificate;

    public function mount(CertificateModel $certificate): void
    {
        abort_unless($certificate->user_id === auth()->id() || auth()->user()->hasControl('issue_certificates'), 404);
        $this->certificate = $certificate;
    }

    public function render(): mixed
    {
        return view('livewire.member.achievements.certificate', [
            'qr' => Certificates::qrSvg($this->certificate),
            'member' => $this->certificate->user,
        ])->title($this->certificate->title());
    }
}
