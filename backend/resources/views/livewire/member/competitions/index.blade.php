<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Competitions') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Show what you know, win prizes and earn certificates.') }}</p>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($open as $competition)
            @php($entry = $entries->get($competition->competition_id))
            <li wire:key="c-{{ $competition->competition_id }}">
                <a wire:navigate href="{{ route('member.competitions.show', $competition->slug) }}" class="flex h-full flex-col rounded-2xl border-4 border-ink bg-white p-5 shadow-[6px_6px_0_var(--color-brand)] transition hover:-translate-y-0.5">
                    <span class="flex flex-wrap gap-2 text-sm font-extrabold">
                        <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $competition->type === 'quiz' ? __('Quiz challenge') : __('Submission') }}</span>
                        @if ($competition->isRunning())<span class="rounded-full bg-savanna px-2.5 py-0.5 text-white">{{ __('Happening now') }}</span>@endif
                        @if ($entry && $entry->status !== 'withdrawn')<span class="rounded-full bg-ink px-2.5 py-0.5 text-brand">{{ __('Registered') }}</span>@endif
                    </span>
                    <span class="mt-3 text-lg font-black leading-snug">{{ $competition->title }}</span>
                    <span class="mt-auto pt-3 text-sm text-slate">{{ $competition->starts_at?->translatedFormat('j M') }} – {{ $competition->ends_at?->translatedFormat('j M Y') }}</span>
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('No competitions are open right now. Check back soon.') }}</li>
        @endforelse
    </ul>

    @if ($finished->isNotEmpty())
        <h2 class="mt-12 text-2xl font-black">{{ __('Finished') }}</h2>
        <ul class="mt-4 divide-y divide-line rounded-2xl border-2 border-line bg-white">
            @foreach ($finished as $competition)
                @php($entry = $entries->get($competition->competition_id))
                <li>
                    <a wire:navigate href="{{ route('member.competitions.show', $competition->slug) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-brand-wash/50">
                        <span class="font-bold">{{ $competition->title }}</span>
                        <span class="text-sm font-bold text-slate">
                            @if ($competition->publication_status === 'cancelled'){{ __('Cancelled') }}
                            @elseif ($competition->results_status === 'published'){{ $entry?->rank ? __('You placed :rank', ['rank' => \App\Support\Certificates::ordinal($entry->rank)]) : __('See results') }}
                            @else{{ __('Results coming soon') }}@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
