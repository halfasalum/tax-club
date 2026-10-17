<?php

namespace App\Livewire\Admin;

use App\Models\NotificationTemplate;
use App\Support\NotificationCatalogue;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Notification wording and channels (NOT-REQ01).
 */
#[Layout('components.layouts.app')]
class NotificationSettings extends Component
{
    public ?string $editing = null;

    public string $bodySw = '';

    public string $bodyEn = '';

    public bool $smsEnabled = true;

    public bool $inboxEnabled = true;

    public ?string $statusMessage = null;

    public function edit(string $key): void
    {
        $class = NotificationCatalogue::find($key) ?? abort(404);
        $template = NotificationTemplate::find($key);

        $this->editing = $key;
        $this->bodySw = $template?->body_sw ?? NotificationCatalogue::defaultText($class, 'sw');
        $this->bodyEn = $template?->body_en ?? NotificationCatalogue::defaultText($class, 'en');
        $this->smsEnabled = $class::required() || ($template?->sms_enabled ?? $class::smsByDefault());
        $this->inboxEnabled = $template?->inbox_enabled ?? true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'bodySw', 'bodyEn');
    }

    public function save(): void
    {
        $class = NotificationCatalogue::find((string) $this->editing) ?? abort(404);

        $this->validate([
            'bodySw' => 'required|string|max:459',
            'bodyEn' => 'required|string|max:459',
        ], [], ['bodySw' => 'Kiswahili', 'bodyEn' => 'English']);

        foreach (['bodySw' => $this->bodySw, 'bodyEn' => $this->bodyEn] as $field => $body) {
            foreach (NotificationCatalogue::mandatoryPlaceholders($class) as $placeholder) {
                if (! str_contains($body, ':'.$placeholder)) {
                    $this->addError($field, __('The message must include :placeholder.', ['placeholder' => ':'.$placeholder]));
                }
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        NotificationTemplate::updateOrCreate(['key' => $this->editing], [
            'body_sw' => $this->bodySw === NotificationCatalogue::defaultText($class, 'sw') ? null : $this->bodySw,
            'body_en' => $this->bodyEn === NotificationCatalogue::defaultText($class, 'en') ? null : $this->bodyEn,
            'sms_enabled' => $class::required() || $this->smsEnabled,
            'inbox_enabled' => $this->inboxEnabled,
            'updated_by' => auth()->id(),
        ]);

        $this->statusMessage = __(':name saved.', ['name' => $class::label()]);
        $this->cancel();
    }

    public function restoreDefaults(string $key): void
    {
        NotificationTemplate::where('key', $key)->first()?->delete();
        $this->statusMessage = __('Default wording and channels restored.');
        $this->cancel();
    }

    public function render(): mixed
    {
        $templates = NotificationTemplate::all()->keyBy('key');

        return view('livewire.admin.notification-settings', [
            'notifications' => collect(NotificationCatalogue::NOTIFICATIONS)->map(fn (string $class) => [
                'class' => $class,
                'key' => $class::key(),
                'label' => $class::label(),
                'template' => $templates->get($class::key()),
                'text' => $templates->get($class::key())?->bodyFor(app()->getLocale()) ?? NotificationCatalogue::defaultText($class, app()->getLocale()),
            ]),
        ])->title(__('Notifications'));
    }
}
