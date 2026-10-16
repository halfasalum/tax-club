@php
    use App\Support\Locales;

    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
        'alumni' => __('Alumni'),
    ];
    $firstName = \Illuminate\Support\Str::before(trim($member->full_name).' ', ' ');
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Hello, :name', ['name' => $firstName]) }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Here is your Tax Club at a glance.') }}</p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[24rem_1fr]">
        {{-- Digital membership card (MEM-REQ02 preview; QR verification comes with MEM-REQ03) --}}
        <section aria-labelledby="card-heading" class="space-y-6">
            <h2 id="card-heading" class="sr-only">{{ __('My membership card') }}</h2>
            <div class="sun-hatch relative overflow-hidden rounded-3xl border-4 border-ink p-6 shadow-[8px_8px_0_var(--color-ink)]">
                <img src="{{ asset('images/tra-logo.webp') }}" alt="" width="208" height="242" class="pointer-events-none absolute -bottom-6 -right-6 h-40 w-auto opacity-10" aria-hidden="true">
                <div class="relative flex items-center gap-3">
                    <img src="{{ asset('images/tra-logo.webp') }}" alt="TRA" width="208" height="242" class="h-11 w-auto">
                    <div class="leading-tight">
                        <p class="font-black">TRA Tax Club</p>
                        <p class="text-xs font-bold text-ink-soft">{{ __('Membership card') }}</p>
                    </div>
                </div>
                <p class="relative mt-8 text-2xl font-black leading-tight">{{ $member->full_name }}</p>
                <p class="relative mt-1 font-bold text-ink-soft">{{ $member->institution?->name }}</p>
                <dl class="relative mt-6 grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-extrabold text-ink-soft">{{ __('Membership no.') }}</dt>
                        <dd class="mt-0.5 font-receipt text-lg font-semibold">{{ $member->membership_id ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-extrabold text-ink-soft">{{ __('Category') }}</dt>
                        <dd class="mt-0.5 text-lg font-black">{{ $categories[$member->user_type] ?? $member->user_type }}</dd>
                    </div>
                </dl>
                <p class="relative mt-6 inline-flex items-center gap-2 rounded-full bg-ink px-3 py-1.5 text-sm font-extrabold text-white">
                    <span class="size-2.5 rounded-full bg-[#4ade80]" aria-hidden="true"></span>{{ __('Active member') }}
                </p>
            </div>

            <dl class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl border-2 border-line bg-white p-5">
                    <dt class="font-extrabold text-slate">{{ __('Points') }}</dt>
                    <dd class="mt-1 text-4xl font-black tabular-nums">{{ number_format($member->total_points ?? 0) }}</dd>
                </div>
                <div class="rounded-2xl border-2 border-line bg-white p-5">
                    <dt class="font-extrabold text-slate">{{ __('Quizzes taken') }}</dt>
                    <dd class="mt-1 text-4xl font-black tabular-nums">{{ number_format($attempts) }}</dd>
                </div>
            </dl>
        </section>

        <div class="space-y-10">
            <section aria-labelledby="learn-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="learn-heading" class="text-2xl font-black tracking-tight">{{ __('Continue learning') }}</h2>
                    <a href="{{ Locales::route('learn.index') }}" class="link">{{ __('All lessons') }}</a>
                </div>
                @if ($lessons->isEmpty())
                    <p class="mt-4 rounded-2xl bg-white p-6 text-lg text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
                @else
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($lessons as $lesson)
                            <li class="flex flex-col rounded-2xl border-2 border-line bg-white p-5">
                                <span class="self-start rounded-full bg-brand px-3 py-0.5 text-sm font-extrabold">{{ __('content_type.'.$lesson->content_type) }}</span>
                                <span class="mt-3 flex-1 text-lg font-black leading-snug">{{ $lesson->title }}</span>
                                @if ($lesson->is_public && $lesson->slug)
                                    <a href="{{ Locales::route('learn.show', ['slug' => $lesson->slug]) }}" class="link mt-3 self-start">{{ __('Open') }}<span class="sr-only">: {{ $lesson->title }}</span></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="events-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="events-heading" class="text-2xl font-black tracking-tight">{{ __('Upcoming events') }}</h2>
                    <a href="{{ Locales::route('events.index') }}" class="link">{{ __('All events') }}</a>
                </div>
                @if ($events->isEmpty())
                    <p class="mt-4 rounded-2xl bg-white p-6 text-lg text-slate">{{ __('No events are scheduled yet. We will notify you when one is announced.') }}</p>
                @else
                    <div class="mt-4 grid gap-3">
                        @foreach ($events as $event)
                            @include('events.partials.row', ['event' => $event])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
