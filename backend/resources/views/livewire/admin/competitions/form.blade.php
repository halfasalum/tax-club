@php($errorText = 'mt-2 font-semibold text-danger')
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ $competition ? route('admin.competitions.show', $competition) : route('admin.competitions.index') }}" class="link">← {{ $competition ? __('Back to the competition') : __('Competitions') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $competition ? __('Edit competition') : __('New competition') }}</h1>

    <form wire:submit="save" class="mt-8 space-y-6" novalidate>
        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <div>
                <label for="title" class="block font-extrabold">{{ __('Title') }}</label>
                <input wire:model="title" id="title" type="text" @class(['field', 'field-error' => $errors->has('title')])>
                @error('title')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="block font-extrabold">{{ __('Purpose and description') }}</label>
                <textarea wire:model="description" id="description" rows="5" @class(['field', 'field-error' => $errors->has('description')])></textarea>
                @error('description')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="rules" class="block font-extrabold">{{ __('Rules') }} <span class="font-semibold text-slate">({{ __('how it is judged, what is allowed') }})</span></label>
                <textarea wire:model="rules" id="rules" rows="4" class="field"></textarea>
            </div>
        </section>

        <section class="space-y-5 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <h2 class="text-xl font-black">{{ __('How members take part') }}</h2>
            <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash"><input type="radio" wire:model.live="type" value="quiz" class="size-4 accent-ink"> {{ __('Quiz challenge (scored automatically)') }}</label>
                <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash"><input type="radio" wire:model.live="type" value="submission" class="size-4 accent-ink"> {{ __('Submission judged by a panel') }}</label>
            </div>
            @if ($type === 'quiz')
                <div>
                    <label for="quiz_id" class="block font-extrabold">{{ __('Quiz') }} <span class="font-semibold text-slate">({{ __('approved quizzes; it opens for the competition period') }})</span></label>
                    <select wire:model="quiz_id" id="quiz_id" @class(['field', 'field-error' => $errors->has('quiz_id')])>
                        <option value="">{{ __('Choose a quiz') }}</option>
                        @foreach ($quizzes as $quiz)<option value="{{ $quiz->quiz_id }}">{{ $quiz->code }} · {{ $quiz->title }}</option>@endforeach
                    </select>
                    @error('quiz_id')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <label for="submission_kind" class="block font-extrabold">{{ __('What participants submit') }}</label>
                    <select wire:model="submission_kind" id="submission_kind" class="field">
                        <option value="text">{{ __('Written essay') }}</option>
                        <option value="link">{{ __('Link (video, slides, website)') }}</option>
                        <option value="file">{{ __('File (PDF or image)') }}</option>
                    </select>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="starts_at" class="block font-extrabold">{{ __('Starts') }}</label>
                    <input wire:model="starts_at" id="starts_at" type="datetime-local" @class(['field', 'field-error' => $errors->has('starts_at')])>
                    @error('starts_at')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ends_at" class="block font-extrabold">{{ __('Ends') }}</label>
                    <input wire:model="ends_at" id="ends_at" type="datetime-local" @class(['field', 'field-error' => $errors->has('ends_at')])>
                    @error('ends_at')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="registration_closes_at" class="block font-extrabold">{{ __('Registration closes') }}</label>
                    <input wire:model="registration_closes_at" id="registration_closes_at" type="datetime-local" class="field">
                    @error('registration_closes_at')<p class="{{ $errorText }}">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="max_participants" class="block font-extrabold">{{ __('Places') }} <span class="font-semibold text-slate">({{ __('empty = no limit') }})</span></label>
                    <input wire:model="max_participants" id="max_participants" type="number" min="1" class="field">
                </div>
                <div>
                    <label for="winners_count" class="block font-extrabold">{{ __('Number of winners') }}</label>
                    <input wire:model="winners_count" id="winners_count" type="number" min="1" max="50" class="field">
                </div>
            </div>
        </section>

        <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            <x-audience-fields :help="__('Only these members can see and enter the competition. Leave empty for all members.')" />
        </section>

        <section class="space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
            @error('submit')<p class="rounded-xl bg-[#fef3f2] p-3 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-ghost">{{ __('Save draft') }}</button>
                @if (! $competition || $competition->publication_status === 'not_published')
                    <button type="button" wire:click="save(true)" class="btn-primary">{{ __('Save and send for review') }}</button>
                @endif
            </div>
        </section>
    </form>
</div>
