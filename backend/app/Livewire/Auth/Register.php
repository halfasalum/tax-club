<?php

namespace App\Livewire\Auth;

use App\Models\Institution;
use App\Support\MemberRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class Register extends Component
{
    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    public string $user_type = 'secondary';

    public string $institution_search = '';

    public string $institution_id = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accepted_terms = false;

    public ?string $reference = null;

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        if (mb_strlen(trim($this->institution_search)) < 2) {
            return collect();
        }

        return Institution::query()
            ->active()
            ->where('type', $this->user_type)
            ->where('name', 'like', '%'.trim($this->institution_search).'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['institution_id', 'name', 'region', 'district']);
    }

    #[Computed]
    public function selectedInstitution(): ?Institution
    {
        return $this->institution_id ? Institution::find($this->institution_id) : null;
    }

    /**
     * Check contact details as soon as the student leaves each field.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['full_name', 'phone', 'email'], true)) {
            return;
        }

        if ($property !== 'full_name') {
            $this->{$property} = MemberRegistration::normalizeInput([$property => $this->{$property}])[$property];
        }

        if (filled($this->{$property}) || $property !== 'email') {
            $this->validateOnly($property, MemberRegistration::rules());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
            'phone.unique' => __('This phone number is already registered. Sign in or recover your account.'),
            'email.unique' => __('This email is already registered. Sign in or recover your account.'),
            'accepted_terms.accepted' => __('You must accept the terms to register.'),
        ];
    }

    public function updatedUserType(): void
    {
        $this->clearInstitution();
    }

    public function selectInstitution(string $institutionId): void
    {
        $this->institution_id = $institutionId;
        $this->institution_search = '';
        $this->resetErrorBag('institution_id');
    }

    public function clearInstitution(): void
    {
        $this->institution_id = '';
        $this->institution_search = '';
    }

    public function register(): void
    {
        // Whole classes often register together from one school network.
        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 30)) {
            throw ValidationException::withMessages([
                'full_name' => __('Too many registrations from this connection. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60)]),
            ]);
        }

        $normalized = MemberRegistration::normalizeInput(['phone' => $this->phone, 'email' => $this->email]);
        $this->phone = $normalized['phone'];
        $this->email = $normalized['email'];

        $validated = $this->validate(MemberRegistration::rules() + ['accepted_terms' => ['accepted']]);

        $user = MemberRegistration::register($validated);
        RateLimiter::hit($throttleKey, 3600);

        $this->reference = $user->registration_reference;
        $this->reset('password', 'password_confirmation');
    }

    public function render(): mixed
    {
        return view('livewire.auth.register')->title(__('Join Tax Club'));
    }
}
