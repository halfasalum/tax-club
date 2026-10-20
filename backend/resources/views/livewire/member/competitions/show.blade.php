@php($active = $entry && in_array($entry->status, ['registered', 'submitted'], true))
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ route('member.competitions') }}" class="link">← {{ __('Competitions') }}</a>
    <header class="sun-hatch mt-4 rounded-3xl border-4 border-ink p-6 sm:p-8">
        <p class="font-extrabold">{{ $competition->type === 'quiz' ? __('Quiz challenge') : __('Submission') }}</p>
        <h1 class="mt-1 text-3xl font-black leading-tight sm:text-4xl">{{ $competition->title }}</h1>
        <p class="mt-3 text-lg font-bold">{{ $competition->starts_at?->translatedFormat('j F Y, H:i') }} – {{ $competition->ends_at?->translatedFormat('j F Y, H:i') }}</p>
    </header>

    @if ($competition->publication_status === 'cancelled')
        <p class="mt-6 rounded-2xl bg-[#fef3f2] p-5 font-bold text-danger">{{ __('This competition was cancelled.') }} {{ $competition->status_reason }}</p>
    @endif
    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('entry')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <div class="max-w-[65ch] whitespace-pre-line text-lg leading-relaxed">{{ $competition->description }}</div>
                @if ($competition->rules)<div class="mt-4 rounded-xl bg-mist p-4"><h2 class="font-black">{{ __('Rules') }}</h2><p class="mt-1 whitespace-pre-line">{{ $competition->rules }}</p></div>@endif
                @if ($prizes->isNotEmpty())
                    <h2 class="mt-5 font-black">{{ __('Prizes') }}</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($prizes as $prize)<li>🏆 {{ $prize->rank_from === $prize->rank_to ? \App\Support\Certificates::ordinal($prize->rank_from) : __('Places :from–:to', ['from' => $prize->rank_from, 'to' => $prize->rank_to]) }}: <span class="font-bold">{{ $prize->reward?->name() }}</span></li>@endforeach
                    </ul>
                @endif
            </section>

            @if ($active && $competition->type === 'submission' && $competition->isRunning())
                <form wire:submit="submitEntry" class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ $entry->status === 'submitted' ? __('Your entry (you can still change it)') : __('Your entry') }}</h2>
                    @if ($competition->submission_kind === 'text')
                        <textarea wire:model="text" rows="10" class="field" aria-label="{{ __('Your entry') }}"></textarea>
                    @elseif ($competition->submission_kind === 'link')
                        <input wire:model="url" type="url" class="field" placeholder="https://" aria-label="{{ __('Link to your entry') }}">
                    @else
                        <input wire:model="file" type="file" accept="application/pdf,image/jpeg,image/png" class="block w-full rounded-xl border-2 border-dashed border-line bg-mist p-4 font-bold" aria-label="{{ __('Your file') }}">
                        @if ($entry->submission_file)<p class="text-sm text-slate">{{ __('A file is already submitted. Uploading a new one replaces it.') }}</p>@endif
                    @endif
                    @error('url')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    @error('file')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Submit entry') }}</button>
                </form>
            @endif

            @if ($results->isNotEmpty())
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Results') }}</h2>
                    <ol class="mt-3 divide-y divide-line">
                        @foreach ($results as $result)
                            <li @class(['flex items-center gap-4 py-2', 'font-black' => $result->user_id === auth()->id()])>
                                <span class="grid size-9 place-items-center rounded-full {{ $result->rank <= $competition->winners_count ? 'bg-brand' : 'bg-mist' }} font-black">{{ $result->rank }}</span>
                                <span class="flex-1">{{ $result->user?->publicName() }} <span class="text-sm font-normal text-slate">{{ $result->user?->institution?->name }}</span></span>
                                <span class="tabular-nums">{{ number_format($result->score, 1) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-3xl border-4 border-ink bg-white p-6">
                @if ($entry && $entry->rank && $competition->results_status === 'published')
                    <p class="text-sm font-extrabold text-slate">{{ __('Your result') }}</p>
                    <p class="text-3xl font-black">{{ \App\Support\Certificates::ordinal($entry->rank) }}</p>
                    <a wire:navigate href="{{ route('member.achievements') }}" class="btn-brand mt-3 w-full">{{ __('My certificates and rewards') }}</a>
                @elseif ($active)
                    <p class="text-2xl font-black">{{ __('You are registered') }}</p>
                    @if ($competition->type === 'quiz' && $competition->quiz)
                        @if ($competition->isRunning())
                            <a wire:navigate href="{{ route('member.quizzes.show', $competition->quiz) }}" class="btn-brand mt-3 w-full">{{ __('Take the quiz') }}</a>
                            <p class="mt-2 text-sm text-slate">{{ __('Your best attempt before the end counts.') }}</p>
                        @else
                            <p class="mt-2 text-slate">{{ __('The quiz opens when the competition starts.') }}</p>
                        @endif
                    @endif
                    @if (! $competition->hasEnded())<button type="button" wire:click="withdraw" wire:confirm="{{ __('Withdraw from this competition?') }}" class="link mt-4 block">{{ __('Withdraw') }}</button>@endif
                @elseif (! $problem)
                    <p class="text-2xl font-black">{{ __('Take part') }}</p>
                    @if ($competition->registration_closes_at)<p class="mt-1 text-sm text-slate">{{ __('Registration closes :date', ['date' => $competition->registration_closes_at->translatedFormat('j M Y, H:i')]) }}</p>@endif
                    <button type="button" wire:click="register" class="btn-primary mt-4 w-full">{{ __('Register') }}</button>
                @else
                    <p class="text-lg font-black">{{ $problem }}</p>
                @endif
            </section>
        </aside>
    </div>
</div>
