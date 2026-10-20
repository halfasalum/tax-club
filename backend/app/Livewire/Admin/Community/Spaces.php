<?php

namespace App\Livewire\Admin\Community;

use App\Livewire\Concerns\ManagesAudience;
use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Models\Institution;
use App\Support\Community;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Create and manage discussion spaces and collaboration groups
 * (COM-REQ05, COM-REQ09).
 */
#[Layout('components.layouts.app')]
class Spaces extends Component
{
    use ManagesAudience;

    public ?int $editingId = null;

    public bool $editing = false;

    public string $name = '';

    public string $description = '';

    public string $type = 'thematic';

    public string $institutionCode = '';

    public bool $members_only = false;

    public bool $moderators_post_only = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('manage_community_spaces'), 403);
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'description', 'type', 'institutionCode', 'members_only', 'moderators_post_only');
        $this->fillAudience(null);
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $space = CommunitySpace::findOrFail($id);
        $this->editingId = $space->id;
        $this->name = $space->name;
        $this->description = (string) $space->description;
        $this->type = $space->type;
        $this->institutionCode = (string) $space->institution?->code;
        $this->members_only = $space->members_only;
        $this->moderators_post_only = $space->moderators_post_only;
        $this->fillAudience($space->audience);
        $this->editing = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|min:3|max:150',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:'.implode(',', CommunitySpace::TYPES),
            'institutionCode' => 'nullable|required_if:type,institution|string',
        ]);
        $audience = $this->audienceRules();

        if ($audience === null) {
            return;
        }

        $institution = $this->type === 'institution' ? Institution::where('code', strtoupper(trim($this->institutionCode)))->first() : null;

        if ($this->type === 'institution' && ! $institution) {
            $this->addError('institutionCode', __('No institution has this code.'));

            return;
        }

        $values = [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'institution_id' => $institution?->institution_id,
            'audience' => $audience,
            'members_only' => $this->members_only,
            'moderators_post_only' => $this->moderators_post_only,
        ];

        if ($this->editingId) {
            Community::updateSpace(CommunitySpace::findOrFail($this->editingId), $values, auth()->user());
        } else {
            Community::createSpace($values, auth()->user());
        }

        $this->editing = false;
        $this->statusMessage = __('Saved.');
    }

    public function setStatus(int $id, string $status): void
    {
        Community::setStatus(CommunitySpace::findOrFail($id), $status, auth()->user());
        $this->statusMessage = __('Saved.');
    }

    public function createDefaults(): void
    {
        Community::ensureDefaultSpaces();
        $this->statusMessage = __('The national space and a space for every active Tax Club are ready.');
    }

    public function render(): mixed
    {
        return view('livewire.admin.community.spaces', [
            'spaces' => CommunitySpace::withCount([
                'posts as posts_count' => fn ($query) => $query->whereNull('parent_id')->where('status', CommunityPost::PUBLISHED),
                'members as members_count',
            ])->orderBy('status')->orderBy('type')->orderBy('name')->get(),
        ])->title(__('Community spaces'));
    }
}
