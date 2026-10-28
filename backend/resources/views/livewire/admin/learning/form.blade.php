@php
    $types = ['article' => __('Article'), 'video' => __('Video'), 'infographic' => __('Infographic'), 'document' => __('Document (PDF)')];
    $levels = ['beginner' => __('Beginner'), 'intermediate' => __('Intermediate'), 'advanced' => __('Advanced')];
    $audiences = ['primary' => __('Primary school'), 'secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $errorText = 'mt-2 font-semibold text-danger';
@endphp
<div class="mx-auto max-w-4xl">
    <a href="{{ $content ? route('admin.learning.show', $content) : route('admin.learning.index') }}" class="link">← {{ $content ? __('Back to the lesson') : __('Learning content') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $content ? __('Edit lesson') : __('New lesson') }}</h1>
    @if ($isRevision)
        <p class="mt-3 rounded-xl bg-brand-wash p-4 font-bold">{{ __('Members keep seeing the published version until your changes are reviewed and published.') }}</p>
    @endif

    <form wire:submit="save" class="mt-8 space-y-6" novalidate>
        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="summary" class="block font-extrabold">{{ __('Summary') }} <span class="font-semibold text-slate">({{ __('shown in lists and search results') }})</span></label>
                <textarea wire:model="summary" id="summary" rows="2" class="field"></textarea>
                @error('summary')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="content_type" class="block font-extrabold">{{ __('Type') }}</label>
                    <select wire:model.live="content_type" id="content_type" class="field">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="category_id" class="block font-extrabold">{{ __('Category') }}</label>
                    <select wire:model="category_id" id="category_id" @class(['field', 'field-error' => $errors->has('category_id')])>
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->id }}">{{ $option->name() }}</option>
                        @endforeach
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
                <div>
                    <label for="language" class="block font-extrabold">{{ __('Language') }}</label>
                    <select wire:model="language" id="language" class="field">
                        <option value="sw">Kiswahili</option>
                        <option value="en">English</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('Lesson') }}</h2>
            <div>
                <label for="objectives" class="block font-extrabold">{{ __('What members will learn') }} <span class="font-semibold text-slate">({{ __('one per line') }})</span></label>
                <textarea wire:model="objectives" id="objectives" rows="3" class="field"></textarea>
            </div>

            @if ($content_type === 'video')
                <div>
                    <label for="file_url" class="block font-extrabold">{{ __('Video link (YouTube or Vimeo)') }}</label>
                    <input wire:model="file_url" id="file_url" type="url" placeholder="https://www.youtube.com/watch?v=…" @class(['field', 'field-error' => $errors->has('file_url')])>
                    @error('file_url')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </div>
            @elseif (in_array($content_type, ['infographic', 'document'], true))
                <div>
                    <p class="font-extrabold">{{ $content_type === 'document' ? __('PDF file') : __('Image') }} <span class="font-semibold text-slate">({{ __('up to 10 MB') }})</span></p>
                    @if ($media_path)
                        <p class="mt-2 flex flex-wrap items-center gap-3 rounded-xl bg-mist p-3">
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($media_path) }}" target="_blank" rel="noopener" class="link break-all">{{ basename($media_path) }}</a>
                            <button type="button" wire:click="removeMedia" class="link">{{ __('Remove') }}</button>
                        </p>
                    @endif
                    <input type="file" wire:model="media" accept="{{ $content_type === 'document' ? 'application/pdf' : 'image/jpeg,image/png,image/webp' }}" class="mt-2 block w-full rounded-xl border-2 border-dashed border-line bg-mist p-4 font-bold">
                    <p wire:loading wire:target="media" class="mt-2 text-slate">{{ __('Uploading…') }}</p>
                    @error('media')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    <label for="file_url" class="mt-4 block font-bold text-slate">{{ __('Or a link to the file') }}</label>
                    <input wire:model="file_url" id="file_url" type="url" class="field">
                </div>
            @endif

            <div>
                <label for="body_markdown" class="block font-extrabold">{{ $content_type === 'article' ? __('Lesson text') : __('Notes shown with the media') }}</label>
                <textarea wire:model="body_markdown" id="body_markdown" rows="14" @class(['field font-receipt text-base', 'field-error' => $errors->has('body_markdown')])></textarea>
                <p class="mt-2 text-sm text-slate">{{ __('Formatting: **bold**, *italic*, ## Heading, - list item, [link text](https://…). A blank line starts a new paragraph.') }}</p>
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('Who it is for and how to find it') }}</h2>
            <fieldset>
                <legend class="font-extrabold">{{ __('Members who can see it') }} <span class="font-semibold text-slate">({{ __('none ticked means everyone') }})</span></legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($audiences as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold">
                            <input type="checkbox" wire:model="audience" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="keywords" class="block font-extrabold">{{ __('Keywords') }} <span class="font-semibold text-slate">({{ __('separate with commas') }})</span></label>
                    <input wire:model="keywords" id="keywords" type="text" placeholder="VAT, EFD, receipts" class="field">
                </div>
                <div>
                    <label for="estimated_minutes" class="block font-extrabold">{{ __('Minutes to complete') }}</label>
                    <input wire:model="estimated_minutes" id="estimated_minutes" type="number" min="1" max="600" class="field">
                </div>
            </div>
            <label class="flex items-start gap-3 rounded-xl bg-mist p-4">
                <input type="checkbox" wire:model="is_public" class="mt-1 size-5 accent-ink">
                <span><span class="font-extrabold">{{ __('Also show on the public website') }}</span><span class="block text-slate">{{ __('Anyone can read it and search engines can find it. Leave unticked for members-only lessons.') }}</span></span>
            </label>
        </section>

        <section class="space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            @if ($isRevision)
                <div>
                    <label for="change_note" class="block font-extrabold">{{ __('What changed in this version?') }}</label>
                    <textarea wire:model="change_note" id="change_note" rows="2" placeholder="{{ __('For example: updated the VAT rate after the Finance Act 2026') }}" class="field"></textarea>
                </div>
            @endif
            @error('submit')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-ghost" wire:loading.attr="disabled">{{ __('Save draft') }}</button>
                <button type="button" wire:click="save(true)" class="btn-primary" wire:loading.attr="disabled">{{ __('Save and send for review') }}</button>
            </div>
        </section>
    </form>
</div>
