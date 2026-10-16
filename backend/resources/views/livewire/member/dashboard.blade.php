@php
    use App\Support\Locales;

    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
        'alumni' => __('Alumni'),
    ];
@endphp
<div class="grid gap-10 lg:grid-cols-[22rem_1fr]">
    {{-- Digital membership card (MEM-REQ02 preview; QR verification comes with MEM-REQ03) --}}
    <section aria-labelledby="card-heading">
        <h1 id="card-heading" class="sr-only">{{ __('My membership card') }}</h1>
        <div class="relative overflow-hidden rounded-xl bg-ink p-6 text-white shadow-[0_24px_48px_-24px_rgba(20,40,75,0.6)]">
            <div class="absolute -right-10 -top-10 size-40 rounded-full bg-flag/90" aria-hidden="true"></div>
            <p class="relative text-sm font-semibold text-white/80">TRA Tax Club</p>
            <p class="relative mt-10 text-2xl font-extrabold leading-tight">{{ $member->full_name }}</p>
            <p class="relative mt-1 text-white/80">{{ $member->institution?->name }}</p>
            <dl class="relative mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-white/60">{{ __('Membership no.') }}</dt>
                    <dd class="mt-0.5 font-receipt text-base font-semibold">{{ $member->membership_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-white/60">{{ __('Category') }}</dt>
                    <dd class="mt-0.5 font-semibold">{{ $categories[$member->user_type] ?? $member->user_type }}</dd>
                </div>
            </dl>
            <p class="relative mt-6 inline-flex items-center gap-2 rounded-full bg-savanna px-3 py-1 text-sm font-semibold">
                <span class="size-2 rounded-full bg-white" aria-hidden="true"></span>{{ __('Active member') }}
            </p>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-line bg-line">
            <div class="bg-white px-5 py-4">
                <dt class="text-sm text-slate">{{ __('Points') }}</dt>
                <dd class="mt-1 text-2xl font-extrabold tabular-nums">{{ number_format($member->total_points ?? 0) }}</dd>
            </div>
            <div class="bg-white px-5 py-4">
                <dt class="text-sm text-slate">{{ __('Quizzes taken') }}</dt>
                <dd class="mt-1 text-2xl font-extrabold tabular-nums">{{ number_format($attempts) }}</dd>
            </div>
        </dl>
    </section>

    <div class="space-y-12">
        <section aria-labelledby="learn-heading">
            <h2 id="learn-heading" class="text-2xl font-extrabold tracking-tight">{{ __('Continue learning') }}</h2>
            @if ($lessons->isEmpty())
                <p class="mt-3 text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
            @else
                <ul class="mt-4 divide-y divide-line rounded-lg border border-line bg-white">
                    @foreach ($lessons as $lesson)
                        <li class="flex items-center justify-between gap-4 px-5 py-4">
                            <span>
                                <span class="block font-semibold">{{ $lesson->title }}</span>
                                <span class="text-sm text-slate">{{ __('content_type.'.$lesson->content_type) }}</span>
                            </span>
                            @if ($lesson->is_public && $lesson->slug)
                                <a href="{{ Locales::route('learn.show', ['slug' => $lesson->slug]) }}" class="shrink-0 text-sm font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4">{{ __('Open') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="events-heading">
            <h2 id="events-heading" class="text-2xl font-extrabold tracking-tight">{{ __('Upcoming events') }}</h2>
            @if ($events->isEmpty())
                <p class="mt-3 text-slate">{{ __('No events are scheduled yet. We will notify you when one is announced.') }}</p>
            @else
                <div class="mt-4 divide-y divide-line border-y border-line">
                    @foreach ($events as $event)
                        @include('events.partials.row', ['event' => $event])
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
