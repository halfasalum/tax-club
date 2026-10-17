<?php

namespace App\Livewire\Admin;

use App\Models\SmsMessage;
use App\Support\NotificationCatalogue;
use App\Support\Sms\SmsGateway;
use App\Support\Sms\SmsRetry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * SMS delivery status and retries (NOT-REQ07).
 */
#[Layout('components.layouts.app')]
class SmsLog extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $purpose = '';

    #[Url]
    public string $search = '';

    public ?string $statusMessage = null;

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        $digits = preg_replace('/\D/', '', $this->search);

        return SmsMessage::query()
            ->with('user:user_id,full_name')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->purpose !== '', fn ($query) => $query->where('purpose', $this->purpose))
            ->when(strlen($digits) >= 4, fn ($query) => $query->where('to', 'like', '%'.ltrim($digits, '0').'%'))
            ->latest('id')
            ->paginate(30);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function lastSevenDays(): array
    {
        $counts = SmsMessage::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            SmsMessage::STATUS_SENT => (int) ($counts[SmsMessage::STATUS_SENT] ?? 0),
            SmsMessage::STATUS_FAILED => (int) ($counts[SmsMessage::STATUS_FAILED] ?? 0),
            SmsMessage::STATUS_PENDING => (int) ($counts[SmsMessage::STATUS_PENDING] ?? 0),
        ];
    }

    public function retry(int $id, SmsGateway $gateway): void
    {
        try {
            $sent = SmsRetry::retry(SmsMessage::findOrFail($id), $gateway);
        } catch (InvalidArgumentException $exception) {
            $this->statusMessage = $exception->getMessage();

            return;
        }

        $this->statusMessage = $sent ? __('The message was sent.') : __('Sending failed again. Check the error and the SMS provider balance.');
        unset($this->messages, $this->lastSevenDays);
    }

    public function render(): mixed
    {
        return view('livewire.admin.sms-log', [
            'purposes' => collect(NotificationCatalogue::NOTIFICATIONS)->mapWithKeys(fn ($class) => [$class::key() => $class::label()]),
        ])->title(__('SMS delivery'));
    }
}
