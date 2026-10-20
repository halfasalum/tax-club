@php
    use App\Support\Locales;

    $firstName = \Illuminate\Support\Str::before(trim($member->full_name).' ', ' ');
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Hello, :name', ['name' => $firstName]) }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Here is your Tax Club at a glance.') }}</p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[24rem_1fr]">
        <section aria-labelledby="card-heading" class="space-y-6">
            <h2 id="card-heading" class="sr-only">{{ __('My membership card') }}</h2>
            <x-membership-card :member="$member" />
            <a wire:navigate href="{{ route('member.card') }}" class="btn-primary w-full">{{ __('Open my card and QR code') }}</a>

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
                    <a wire:navigate href="{{ route('member.learn') }}" class="link">{{ __('All lessons') }}</a>
                </div>
                @if ($lessons->isEmpty())
                    <p class="mt-4 rounded-2xl bg-white p-6 text-lg text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
                @else
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($lessons as $lesson)
                            <li class="flex flex-col rounded-2xl border-2 border-line bg-white p-5">
                                <span class="self-start rounded-full bg-brand px-3 py-0.5 text-sm font-extrabold">{{ __('content_type.'.$lesson->content_type) }}</span>
                                <span class="mt-3 flex-1 text-lg font-black leading-snug">{{ $lesson->title }}</span>
                                <a wire:navigate href="{{ route('member.learn.lesson', $lesson) }}" class="link mt-3 self-start">{{ __('Open') }}<span class="sr-only">: {{ $lesson->title }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="events-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="events-heading" class="text-2xl font-black tracking-tight">{{ __('Upcoming events') }}</h2>
                    <a wire:navigate href="{{ route('member.events') }}" class="link">{{ __('All events') }}</a>
                </div>
                @if ($events->isEmpty())
                    <p class="mt-4 rounded-2xl bg-white p-6 text-lg text-slate">{{ __('No events are scheduled yet. We will notify you when one is announced.') }}</p>
                @else
                    <div class="mt-4 grid gap-3">
                        @foreach ($events as $event)
                            @include('events.partials.row', ['event' => $event, 'memberLink' => true])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
