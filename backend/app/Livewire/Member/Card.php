<?php

namespace App\Livewire\Member;

use App\Support\MembershipCard;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Card extends Component
{
    public ?string $statusMessage = null;

    /**
     * Replace the QR code after a card was lost or shared (MEM-REQ02).
     */
    public function reissue(): void
    {
        $member = auth()->user();
        abort_unless(MembershipCard::isValid($member), 403);

        MembershipCard::reissue($member);
        $this->statusMessage = __('Your card has a new QR code. Old copies of your card no longer verify.');
    }

    public function render(): mixed
    {
        $member = auth()->user()->load('institution:institution_id,name,region');
        if (MembershipCard::isValid($member) && ! $member->card_token) {
            MembershipCard::issue($member);
        }

        return view('livewire.member.card', [
            'member' => $member,
            'qr' => MembershipCard::isValid($member) ? MembershipCard::qrSvg($member) : null,
        ])->title(__('My membership card'));
    }
}
