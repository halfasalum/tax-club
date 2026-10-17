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

    public string $date_of_birth = '';

    public string $gender = '';

    public string $guardian_name = '';

    public string $guardian_phone = '';

    public string $guardian_relationship = 'parent';

    public bool $guardian_consent = false;

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
     * Whether the date of birth entered makes the applicant a minor, which
     * shows the guardian section.
     */
    #[Computed]
    public function isMinor(): bool
    {
        return MemberRegistration::isMinorDateOfBirth($this->date_of_birth);
    }

    /**
     * Check each field as soon as the student leaves it.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['full_name', 'phone', 'email', 'date_of_birth', 'guardian_name', 'guardian_phone'], true)) {
            return;
        }

        if (in_array($property, ['phone', 'email', 'guardian_phone'], true)) {
            $this->{$property} = MemberRegistration::normalizeInput([$property => $this->{$property}])[$property];
        }

        unset($this->isMinor);

        if (filled($this->{$property}) || ! in_array($property, ['email', 'guardian_name', 'guardian_phone'], true)) {
            $rules = MemberRegistration::rules($this->all());

            if (isset($rules[$property])) {
                $this->validateOnly($property, $rules);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return MemberRegistration::messages();
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

        $normalized = MemberRegistration::normalizeInput(['phone' => $this->phone, 'email' => $this->email, 'guardian_phone' => $this->guardian_phone]);
        $this->phone = $normalized['phone'];
        $this->email = $normalized['email'];
        $this->guardian_phone = $normalized['guardian_phone'];

        $validated = $this->validate(MemberRegistration::rules($this->all()) + ['accepted_terms' => ['accepted']]);

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
