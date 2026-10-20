<?php

namespace App\Livewire\Admin\Community;

use App\Models\CommunitySpace;
use App\Models\User;
use App\Support\Community;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Members and moderators of a space or collaboration group (COM-REQ09).
 */
#[Layout('components.layouts.app')]
class Members extends Component
{
    public CommunitySpace $space;

    public string $numbers = '';

    public string $role = 'member';

    public ?string $statusMessage = null;

    public function mount(CommunitySpace $space): void
    {
        abort_unless(auth()->user()->hasControl('manage_community_spaces'), 403);
        $this->space = $space;
    }

    public function add(): void
    {
        $this->validate(['numbers' => 'required|string|max:5000', 'role' => 'required|in:member,moderator']);
        $result = Community::addMembers($this->space, $this->numbers, $this->role, auth()->user());
        $this->numbers = '';
        $this->statusMessage = trans_choice(':count member added.|:count members added.', $result['added'], ['count' => $result['added']])
            .($result['unknown'] ? ' '.__('Not found: :numbers', ['numbers' => implode(', ', $result['unknown'])]) : '');
    }

    public function remove(string $userId): void
    {
        Community::removeMember($this->space, User::findOrFail($userId), auth()->user());
    }

    public function render(): mixed
    {
        return view('livewire.admin.community.members', [
            'members' => $this->space->members()->with('institution:institution_id,name')->orderByPivot('role', 'desc')->orderBy('full_name')->get(),
        ])->title($this->space->name);
    }
}
