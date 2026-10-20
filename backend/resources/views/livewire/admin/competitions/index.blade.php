@php($views = ['all' => __('All'), 'review' => __('Waiting for review'), 'live' => __('Live'), 'results' => __('Results to approve'), 'done' => __('Results published')])
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Competitions') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Quiz challenges and essay, poster or video competitions, with judging, approved results, certificates and prizes.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->hasControl('manage_rewards') || auth()->user()->hasControl('award_rewards'))<a wire:navigate href="{{ route('admin.rewards') }}" class="btn-ghost">{{ __('Rewards') }}</a>@endif
            @if (auth()->user()->hasControl('issue_certificates'))<a wire:navigate href="{{ route('admin.certificates') }}" class="btn-ghost">{{ __('Certificates') }}</a>@endif
            @if ($canCreate)<a wire:navigate href="{{ route('admin.competitions.create') }}" class="btn-brand">{{ __('New competition') }}</a>@endif
        </div>
    </div>
    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if (($counts[$value] ?? 0) > 0)<span class="ml-1 rounded-full bg-brand px-2 text-sm text-ink">{{ $counts[$value] }}</span>@endif
            </button>
        @endforeach
    </div>
    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white">
        <table class="w-full min-w-[48rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Competition') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Dates') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Participants') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($competitions as $competition)
                    <tr wire:key="c-{{ $competition->competition_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.competitions.show', $competition) }}" class="font-black hover:underline">{{ $competition->title }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $competition->code }}</span> · {{ $competition->type === 'quiz' ? __('Quiz') : __('Submissions') }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $competition->starts_at?->translatedFormat('j M') }} – {{ $competition->ends_at?->translatedFormat('j M Y') }}</td>
                        <td class="px-4 py-3">
                            <x-editorial-status :status="$competition->editorial_status" />
                            @if ($competition->publication_status !== 'not_published')<x-editorial-status :status="$competition->displayStatus()" />@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($competition->entries_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('No competitions yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
