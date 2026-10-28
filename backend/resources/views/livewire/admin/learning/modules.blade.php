@php
    use App\Models\LearningModule;

    $levels = ['beginner' => __('Beginner'), 'intermediate' => __('Intermediate'), 'advanced' => __('Advanced')];
    $audiences = ['primary' => __('Primary school'), 'secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.learning.index') }}" class="link">← {{ __('Learning content') }}</a>
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Modules') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Put lessons in order so members can work through a topic step by step. A module is complete when all required lessons are done.') }}</p>
        </div>
        @if ($canPublish && ! $editing)
            <button type="button" wire:click="create" class="btn-brand">{{ __('New module') }}</button>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    @if ($editing)
        <form wire:submit="save" class="mt-8 space-y-5 rounded-3xl border-4 border-ink bg-white p-6 sm:p-8">
            <h2 class="text-2xl font-black">{{ $editingId ? __('Edit module') : __('New module') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                    <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                    @error('title')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="summary" class="block font-extrabold">{{ __('Summary') }}</label>
                    <textarea wire:model="summary" id="summary" rows="2" class="field"></textarea>
                </div>
                <div>
                    <label for="category_id" class="block font-extrabold">{{ __('Category') }}</label>
                    <select wire:model="category_id" id="category_id" class="field">
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->id }}">{{ $option->name() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="language" class="block font-extrabold">{{ __('Language') }}</label>
                    <select wire:model="language" id="language" class="field">
                        <option value="">{{ __('Any language') }}</option>
                        <option value="sw">Kiswahili</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div>
                    <label for="level" class="block font-extrabold">{{ __('Level') }}</label>
                    <select wire:model="level" id="level" class="field">
                        @foreach ($levels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <fieldset>
                <legend class="font-extrabold">{{ __('Members who can see it') }} <span class="font-semibold text-slate">({{ __('none ticked means everyone') }})</span></legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($audiences as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold"><input type="checkbox" wire:model="audience" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <p class="font-extrabold">{{ __('Lessons in order') }}</p>
                <ol class="mt-2 space-y-2">
                    @forelse ($items as $index => $item)
                        <li class="flex flex-wrap items-center gap-3 rounded-xl border-2 border-line p-3" wire:key="item-{{ $item['content_id'] }}">
                            <span class="grid size-8 place-items-center rounded-full bg-brand font-black">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 font-bold">{{ $lessonTitles[$item['content_id']] ?? '—' }}</span>
                            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" wire:model="items.{{ $index }}.is_required" class="size-4 accent-ink"> {{ __('Required') }}</label>
                            <button type="button" wire:click="move({{ $index }}, -1)" class="rounded-lg px-2 py-1 font-black hover:bg-mist" aria-label="{{ __('Move up') }}" @disabled($index === 0)>↑</button>
                            <button type="button" wire:click="move({{ $index }}, 1)" class="rounded-lg px-2 py-1 font-black hover:bg-mist" aria-label="{{ __('Move down') }}" @disabled($index === count($items) - 1)>↓</button>
                            <button type="button" wire:click="removeItem({{ $index }})" class="link text-sm">{{ __('Remove') }}</button>
                        </li>
                    @empty
                        <li class="rounded-xl bg-mist p-4 text-slate">{{ __('No lessons yet. Add published lessons below.') }}</li>
                    @endforelse
                </ol>
                @error('items')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                <label for="addLesson" class="mt-4 block font-bold">{{ __('Add a lesson') }}</label>
                <select wire:model.live="addLesson" id="addLesson" class="field">
                    <option value="">{{ __('Choose a published lesson') }}</option>
                    @foreach ($this->availableLessons as $lesson)
                        <option value="{{ $lesson->content_id }}" @disabled(collect($items)->contains('content_id', $lesson->content_id))>{{ $lesson->code }} · {{ $lesson->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-ghost">{{ __('Save') }}</button>
                <button type="button" wire:click="save(true)" class="btn-primary">{{ __('Save and publish') }}</button>
                <button type="button" wire:click="cancel" class="link ml-auto">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <ul class="mt-8 grid gap-4 md:grid-cols-2">
        @forelse ($modules as $module)
            <li class="rounded-2xl border-2 border-line bg-white p-5" wire:key="module-{{ $module->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-lg font-black">{{ $module->title }}</p>
                        <p class="text-sm text-slate">{{ $module->category?->name() ?? '—' }} · {{ trans_choice(':count lesson|:count lessons', $module->lessons_count, ['count' => $module->lessons_count]) }}</p>
                    </div>
                    <x-editorial-status :status="$module->status === 'draft' ? 'draft' : $module->status" />
                </div>
                @if ($canPublish)
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" wire:click="edit({{ $module->id }})" class="btn-ghost py-1.5 text-sm">{{ __('Edit') }}</button>
                        @if ($module->status === LearningModule::PUBLISHED)
                            <button type="button" wire:click="setStatus({{ $module->id }}, 'draft')" class="btn-ghost py-1.5 text-sm">{{ __('Unpublish') }}</button>
                        @else
                            <button type="button" wire:click="setStatus({{ $module->id }}, 'published')" class="btn-primary py-1.5 text-sm" @disabled($module->lessons_count === 0)>{{ __('Publish') }}</button>
                        @endif
                        @if ($module->status !== LearningModule::ARCHIVED)
                            <button type="button" wire:click="setStatus({{ $module->id }}, 'archived')" wire:confirm="{{ __('Archive this module? Members keep their completion history.') }}" class="link text-sm">{{ __('Archive') }}</button>
                        @endif
                    </div>
                @endif
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate md:col-span-2">{{ __('No modules yet.') }}</li>
        @endforelse
    </ul>
</div>
