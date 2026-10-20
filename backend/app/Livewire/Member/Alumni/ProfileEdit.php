<?php

namespace App\Livewire\Member\Alumni;

use App\Support\AlumniNetwork;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * An alumnus's network profile and directory choice (ALM-REQ01/02).
 */
#[Layout('components.layouts.app')]
class ProfileEdit extends Component
{
    public ?int $graduation_year = null;

    public string $occupation = '';

    public string $organization = '';

    public string $field = '';

    public string $bio = '';

    public string $skills = '';

    public string $linkedin_url = '';

    public bool $show_in_directory = false;

    public bool $open_to_mentoring = false;

    public bool $saved = false;

    public function mount(): void
    {
        abort_unless(AlumniNetwork::isAlumnus(auth()->user()), 404);
        $profile = AlumniNetwork::profileFor(auth()->user());

        foreach (['occupation', 'organization', 'field', 'bio', 'skills', 'linkedin_url'] as $field) {
            $this->{$field} = (string) $profile->{$field};
        }

        $this->graduation_year = $profile->graduation_year ?? auth()->user()->alumni_since?->year;
        $this->show_in_directory = (bool) $profile->show_in_directory;
        $this->open_to_mentoring = (bool) $profile->open_to_mentoring;
    }

    public function save(): void
    {
        $this->validate([
            'graduation_year' => 'nullable|integer|min:1990|max:'.(now()->year + 1),
            'occupation' => 'nullable|string|max:150',
            'organization' => 'nullable|string|max:150',
            'field' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'skills' => 'nullable|string|max:300',
            'linkedin_url' => 'nullable|url|max:300|regex:/^https:\/\/([a-z]+\.)?linkedin\.com\//i',
        ], ['linkedin_url.regex' => __('Use your LinkedIn profile link (https://www.linkedin.com/in/…).')]);

        try {
            AlumniNetwork::saveProfile(auth()->user(), $this->only(['graduation_year', 'occupation', 'organization', 'field', 'bio', 'skills', 'linkedin_url', 'show_in_directory', 'open_to_mentoring']));
        } catch (InvalidArgumentException $exception) {
            $this->addError('bio', $exception->getMessage());

            return;
        }

        $this->saved = true;
    }

    public function render(): mixed
    {
        return view('livewire.member.alumni.profile-edit')->title(__('My alumni profile'));
    }
}
