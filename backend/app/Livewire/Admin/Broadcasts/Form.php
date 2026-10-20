<?php

namespace App\Livewire\Admin\Broadcasts;

use App\Livewire\Concerns\ManagesAudience;
use App\Models\Broadcast;
use App\Models\User;
use App\Support\Audience;
use App\Support\Broadcasts;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Write a broadcast, choose the audience and channels, and see how many
 * members and SMS it will reach before sending it for approval (NOT-REQ03).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    use ManagesAudience;

    public ?Broadcast $broadcast = null;

    public string $title = '';

    public string $message_sw = '';

    public string $message_en = '';

    public string $link_url = '';

    public bool $send_sms = true;

    public bool $send_inbox = true;

    public function mount(?Broadcast $broadcast = null): void
    {
        abort_unless(auth()->user()->hasControl('create_broadcast'), 403);

        if (! $broadcast?->exists) {
            return;
        }

        $this->broadcast = $broadcast;

        foreach (['title', 'message_sw', 'message_en', 'link_url'] as $field) {
            $this->{$field} = (string) $broadcast->{$field};
        }

        $this->send_sms = $broadcast->send_sms;
        $this->send_inbox = $broadcast->send_inbox;
        $this->fillAudience($broadcast->audience);
    }

    public function save(bool $submit = false): mixed
    {
        $this->validate([
            'title' => 'required|string|max:200',
            'message_sw' => 'required|string|max:'.Broadcasts::MAX_LENGTH,
            'message_en' => 'nullable|string|max:'.Broadcasts::MAX_LENGTH,
            'link_url' => 'nullable|url|max:500',
        ]);
        $audience = $this->audienceRules();

        if ($audience === null) {
            return null;
        }

        $fields = [
            'title' => trim($this->title),
            'message_sw' => trim($this->message_sw),
            'message_en' => trim($this->message_en),
            'link_url' => trim($this->link_url),
            'send_sms' => $this->send_sms,
            'send_inbox' => $this->send_inbox,
            'audience' => $audience,
        ];
        $user = auth()->user();

        try {
            if ($this->broadcast) {
                Broadcasts::update($this->broadcast, $fields, $user);
            } else {
                $this->broadcast = Broadcasts::create($fields, $user);
            }

            if ($submit) {
                Broadcasts::submit($this->broadcast->refresh(), $user);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('submit', $exception->getMessage());

            return null;
        }

        session()->flash('status', $submit ? __('Sent for approval.') : __('Saved.'));

        return $this->redirectRoute('admin.broadcasts.show', $this->broadcast, navigate: true);
    }

    public function render(): mixed
    {
        $preview = new Broadcast(['message_sw' => $this->message_sw, 'message_en' => $this->message_en]);
        $rules = Audience::normalize(['categories' => $this->audienceCategories, 'regions' => $this->audienceRegions, 'age' => $this->audienceAge]);
        $recipients = Audience::members(User::query(), $rules)->count();

        return view('livewire.admin.broadcasts.form', [
            'recipients' => $recipients,
            'smsParts' => $preview->smsParts(),
        ])->title($this->broadcast ? __('Edit broadcast') : __('New broadcast'));
    }
}
