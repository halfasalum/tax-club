@php
    $types = \App\Support\Community::typeLabels();
    $statuses = ['active' => __('Active'), 'locked' => __('Read-only'), 'archived' => __('Archived')];
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Community spaces') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Discussion spaces for the whole country, each Tax Club, topics, events and competitions, and collaboration groups across institutions.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="createDefaults" class="btn-ghost">{{ __('Create default spaces') }}</button>
            <button type="button" wire:click="create" class="btn-brand">{{ __('New space or group') }}</button>
        </div>
    </div>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    @if ($editing)
        <form wire:submit="save" class="mt-6 space-y-5 rounded-3xl border-4 border-ink bg-white p-6">
            <h2 class="text-xl font-black">{{ $editingId ? __('Edit space') : __('New space or group') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="block font-bold">{{ __('Name') }}</label>
                    <input wire:model="name" id="name" type="text" @class(['field', 'field-error' => $errors->has('name')])>
                    @error('name')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="type" class="block font-bold">{{ __('Type') }}</label>
                    <select wire:model.live="type" id="type" class="field">
                        @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="description" class="block font-bold">{{ __('Description') }}</label>
                <textarea wire:model="description" id="description" rows="2" class="field"></textarea>
            </div>
            @if ($type === 'institution')
                <div>
                    <label for="institutionCode" class="block font-bold">{{ __('Institution code') }}</label>
                    <input wire:model="institutionCode" id="institutionCode" type="text" class="field font-receipt sm:max-w-xs">
                    @error('institutionCode')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @elseif ($type === 'group')
                <p class="rounded-xl bg-brand-wash p-3 font-bold">{{ __('Groups are for chosen members only. Add members and moderators after saving.') }}</p>
            @elseif (in_array($type, ['national', 'thematic'], true))
                <x-audience-fields :help="__('Leave empty for all members.')" />
            @endif
            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="members_only" class="size-4 accent-ink" @disabled($type === 'group')> {{ __('Only members added to the space can see it') }}</label>
            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="moderators_post_only" class="size-4 accent-ink"> {{ __('Only moderators start posts (members can comment)') }}</label>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
                <button type="button" wire:click="$set('editing', false)" class="btn-ghost">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Space') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Posts') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Members') }}</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($spaces as $space)
                    <tr wire:key="s-{{ $space->id }}">
                        <td class="px-4 py-3"><span class="font-black">{{ $space->name }}</span><span class="block text-sm text-slate">{{ $types[$space->type] ?? $space->type }}@if ($space->members_only) · {{ __('members only') }}@endif</span></td>
                        <td class="px-4 py-3">
                            <select wire:change="setStatus({{ $space->id }}, $event.target.value)" class="field mt-0 w-36 py-1.5" aria-label="{{ __('Status') }}">
                                @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($space->status === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $space->posts_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $space->members_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="edit({{ $space->id }})" class="link">{{ __('Edit') }}</button>
                            <a wire:navigate href="{{ route('admin.community.members', $space) }}" class="link ml-3">{{ __('Members and moderators') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center font-bold text-slate">{{ __('No spaces yet. Create the default spaces to begin.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
