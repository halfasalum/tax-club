<?php

namespace App\Livewire\Admin\Institutions;

use App\Models\Institution;
use App\Support\InstitutionRegistry;
use App\Support\MemberRegistration;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Register a new institution or update an existing one (INS-REQ01, INS-REQ02).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Institution $institution = null;

    public string $name = '';

    public string $type = 'secondary';

    public string $region = '';

    public string $district = '';

    public string $contact_name = '';

    public string $contact_phone = '';

    public string $contact_email = '';

    public ?string $duplicateWarning = null;

    public function mount(?Institution $institution = null): void
    {
        $this->institution = $institution?->exists ? $institution : null;

        abort_unless(auth()->user()->hasControl($this->institution ? 'edit_institution' : 'add_institution'), 403);

        if ($this->institution) {
            $this->fill($this->institution->only(['name', 'type', 'region', 'district']));
            $this->contact_name = (string) $this->institution->contact_name;
            $this->contact_phone = (string) $this->institution->contact_phone;
            $this->contact_email = (string) $this->institution->contact_email;
        }
    }

    /**
     * Warn about a likely duplicate while the officer is still typing.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['name', 'region', 'district'], true)) {
            return;
        }

        $duplicate = filled($this->name) && filled($this->region) && filled($this->district)
            ? InstitutionRegistry::findDuplicate($this->name, $this->region, $this->district, $this->institution?->institution_id)
            : null;

        $this->duplicateWarning = $duplicate
            ? __('This institution is already registered as :name (:code).', ['name' => $duplicate->name, 'code' => $duplicate->code])
            : null;
    }

    public function save(): mixed
    {
        abort_unless(auth()->user()->hasControl($this->institution ? 'edit_institution' : 'add_institution'), 403);

        $this->contact_phone = MemberRegistration::compactPhone($this->contact_phone);

        $data = $this->validate(InstitutionRegistry::rules(), [
            'contact_phone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
        ]);

        try {
            $institution = $this->institution
                ? InstitutionRegistry::update($this->institution, $data, auth()->user())
                : InstitutionRegistry::create($data, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return null;
        }

        session()->flash('status', $this->institution ? __('Institution updated.') : __('Institution registered with code :code.', ['code' => $institution->code]));

        return $this->redirectRoute('admin.institutions.show', ['institution' => $institution->institution_id]);
    }

    public function render(): mixed
    {
        return view('livewire.admin.institutions.form', [
            'regions' => array_values(config('tanzania.regions')),
        ])->title($this->institution ? __('Edit institution') : __('Register institution'));
    }
}
