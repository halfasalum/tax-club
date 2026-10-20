<div class="inline-block">
    @if ($sent)
        <span class="text-sm font-bold text-savanna" role="status">{{ __('Reported. Thank you.') }}</span>
    @else
        <button type="button" wire:click="$set('open', true)" class="text-sm font-bold text-slate underline-offset-2 hover:text-danger hover:underline">{{ __('Report') }}</button>
    @endif

    @if ($open)
        <div class="fixed inset-0 z-[70] grid place-items-center bg-ink/60 p-4" role="dialog" aria-modal="true" aria-labelledby="report-title-{{ $this->getId() }}" x-data x-on:keydown.escape.window="$wire.set('open', false)">
            <form wire:submit="send" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white p-6 text-left shadow-2xl">
                <h2 id="report-title-{{ $this->getId() }}" class="text-xl font-black">{{ __('What is wrong?') }}</h2>
                <p class="mt-1 text-slate">{{ __('Moderators will look at it. The person you report is not told who reported them.') }}</p>
                <div class="mt-4 grid gap-2">
                    @foreach ($categories as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border-2 border-line px-3 py-2 font-bold has-[:checked]:border-ink has-[:checked]:bg-brand-wash">
                            <input type="radio" wire:model="category" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('category')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                <label for="details-{{ $this->getId() }}" class="mt-4 block font-bold">{{ __('Anything else we should know?') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                <textarea wire:model="details" id="details-{{ $this->getId() }}" rows="3" class="field"></textarea>
                <p class="mt-3 rounded-xl bg-brand-wash p-3 text-sm font-bold">{{ __('If someone is in danger right now, call 112 or tell a teacher or your patron.') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="btn-primary">{{ __('Send report') }}</button>
                    <button type="button" wire:click="$set('open', false)" class="btn-ghost">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
