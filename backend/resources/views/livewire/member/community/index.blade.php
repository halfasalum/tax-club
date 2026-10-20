@php($types = \App\Support\Community::typeLabels())
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Community') }}</h1>
    <p class="mt-1 max-w-[65ch] text-lg text-slate">{{ __('Discuss taxes, share ideas and work together with Tax Club members. Moderators keep it safe.') }}</p>

    <details class="mt-6 rounded-2xl border-2 border-ink bg-brand-wash p-5" open>
        <summary class="cursor-pointer text-lg font-black">{{ __('Community rules') }}</summary>
        <ul class="mt-3 list-disc space-y-1 pl-6">
            <li>{{ __('Be kind and respectful. No bullying, insults or hate.') }}</li>
            <li>{{ __('Never share phone numbers, emails, addresses or social media names. Posts with contact details wait for a moderator.') }}</li>
            <li>{{ __('Do not ask anyone to meet you or move to another app.') }}</li>
            <li>{{ __('Share correct tax information. If you are not sure, Ask TRA.') }}</li>
            <li>{{ __('See something wrong? Press Report. Moderators will act.') }}</li>
        </ul>
    </details>

    <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($spaces as $space)
            <li wire:key="space-{{ $space->id }}">
                <a wire:navigate href="{{ route('member.community.space', $space) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                    <span class="flex flex-wrap gap-2 text-sm font-extrabold">
                        <span class="rounded-full bg-brand px-2.5 py-0.5">{{ $types[$space->type] ?? $space->type }}</span>
                        @if ($space->status === 'locked')<span class="rounded-full bg-mist px-2.5 py-0.5">{{ __('Read-only') }}</span>@endif
                    </span>
                    <span class="mt-3 text-lg font-black leading-snug">{{ $space->name }}</span>
                    @if ($space->description)<span class="mt-1 text-slate">{{ \Illuminate\Support\Str::limit($space->description, 110) }}</span>@endif
                    <span class="mt-auto pt-3 text-sm font-bold">{{ trans_choice(':count post|:count posts', $space->posts_count, ['count' => $space->posts_count]) }}</span>
                </a>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center text-lg font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('No discussion spaces yet.') }}</li>
        @endforelse
    </ul>
</div>
