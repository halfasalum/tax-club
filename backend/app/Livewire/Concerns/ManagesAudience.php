<?php

namespace App\Livewire\Concerns;

use App\Models\Institution;
use App\Support\Audience;

/**
 * Audience fields for forms that target members (EVT-REQ01, OPP-REQ05,
 * NOT-REQ03). Pair with the <x-audience-fields /> component.
 */
trait ManagesAudience
{
    /** @var list<string> */
    public array $audienceCategories = [];

    /** @var list<string> */
    public array $audienceRegions = [];

    public string $audienceAge = Audience::ANY_AGE;

    /** Institution codes, separated by commas. */
    public string $audienceInstitutions = '';

    /**
     * @param  array<string, mixed>|null  $rules
     */
    protected function fillAudience(?array $rules): void
    {
        $rules = Audience::normalize($rules);
        $this->audienceCategories = $rules['categories'];
        $this->audienceRegions = $rules['regions'];
        $this->audienceAge = $rules['age'];
        $this->audienceInstitutions = Institution::whereIn('institution_id', $rules['institutions'])->orderBy('code')->pluck('code')->implode(', ');
    }

    /**
     * The rules from the form; adds a validation error for unknown codes.
     *
     * @return array{categories: list<string>, regions: list<string>, institutions: list<string>, age: string}|null
     */
    protected function audienceRules(): ?array
    {
        $codes = collect(preg_split('/[\s,;]+/', strtoupper($this->audienceInstitutions)) ?: [])->filter()->unique()->values();
        $found = Institution::whereIn('code', $codes)->pluck('institution_id', 'code');
        $unknown = $codes->diff($found->keys());

        if ($unknown->isNotEmpty()) {
            $this->addError('audienceInstitutions', __('Unknown institution codes: :codes', ['codes' => $unknown->implode(', ')]));

            return null;
        }

        return Audience::normalize([
            'categories' => $this->audienceCategories,
            'regions' => $this->audienceRegions,
            'institutions' => $found->values()->all(),
            'age' => $this->audienceAge,
        ]);
    }
}
