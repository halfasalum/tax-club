@php use App\Support\Locales; @endphp
<x-layouts.public>
    {{-- Hero: TRA yellow, with the receipt printing out of a black EFD machine --}}
    <section class="sun-hatch overflow-hidden border-b-4 border-ink">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:py-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-ink px-4 py-1.5 text-sm font-extrabold text-brand">
                    <img src="{{ asset('images/tra-logo.webp') }}" alt="" width="208" height="242" class="h-5 w-auto rounded-sm bg-white p-px">
                    {{ __('Tanzania Revenue Authority') }}
                </p>
                <h1 class="mt-6 max-w-[15ch] text-[2.75rem] font-black leading-[1.02] tracking-[-0.025em] sm:text-6xl lg:text-[4.25rem]">
                    {{ __('Taxes build our schools, roads and hospitals.') }}
                </h1>
                <p class="mt-6 max-w-[54ch] text-lg font-semibold leading-relaxed text-ink-soft sm:text-xl">
                    {{ __('Tax Club brings students in secondary schools, colleges and universities together to learn how taxation works, test what they know and take part in national activities run by TRA.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-primary px-7 text-lg">{{ __('Join Tax Club') }}</a>
                    <a href="{{ Locales::route('learn.index') }}" class="btn-ghost px-7 text-lg">{{ __('Browse lessons') }}</a>
                </div>
                <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 font-bold text-ink-soft">
                    @foreach ([__('Free for students'), __('Kiswahili and English'), __('Digital membership card')] as $point)
                        <li class="flex items-center gap-2">
                            <svg viewBox="0 0 20 20" class="size-5 rounded-full bg-ink p-0.5 text-brand" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
                @if ($memberCount > 0 && $institutionCount > 0)
                    <p class="mt-6 text-sm font-bold text-ink-soft">
                        {{ trans_choice(':count member|:count members', $memberCount, ['count' => number_format($memberCount)]) }},
                        {{ trans_choice(':count institution|:count institutions', $institutionCount, ['count' => number_format($institutionCount)]) }}
                    </p>
                @endif
            </div>

            <div class="relative mx-auto w-full max-w-md">
                {{-- EFD machine --}}
                <div class="rounded-[1.75rem] bg-ink px-6 pb-12 pt-5 shadow-[0_30px_60px_-20px_rgb(0_0_0/0.55)] sm:px-9">
                    <div class="flex items-center justify-between gap-4" aria-hidden="true">
                        <span class="rounded-md bg-[#1d2a12] px-3 py-1.5 font-receipt text-sm font-semibold tracking-wider text-[#c6f36b]">TRA·EFD &nbsp;TSh 0.00</span>
                        <span class="flex gap-1.5">
                            <span class="size-2.5 rounded-full bg-brand"></span>
                            <span class="size-2.5 rounded-full bg-white/25"></span>
                        </span>
                    </div>
                    <div class="mx-auto mb-[-2px] mt-5 h-3 max-w-[23rem] rounded-full bg-black shadow-[inset_0_2px_3px_rgb(255_255_255/0.08)]" aria-hidden="true"></div>
                    <x-receipt :items="[
                        __('Tax lessons'),
                        __('Quizzes and badges'),
                        __('National events'),
                        __('Digital card'),
                        __('Certificates'),
                    ]" />
                </div>
            </div>
        </div>
    </section>

    {{-- How to join: a real sequence, so it is numbered --}}
    <section class="mx-auto max-w-7xl px-4 pt-20 sm:px-6" aria-labelledby="join-heading">
        <h2 id="join-heading" class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('How to join') }}</h2>
        <div class="relative mt-10">
        <div class="absolute left-7 right-[calc(33.333%-3.1rem)] top-7 hidden h-1 rounded bg-brand md:block" aria-hidden="true"></div>
        <ol class="relative grid gap-8 md:grid-cols-3">
            @foreach ([
                [__('Register'), __('Sign up on the website or the Tax Club app and choose your school, college or university.')],
                [__('Get confirmed'), __('Your Tax Club patron or TRA coordinator confirms that you are a member.')],
                [__('Start learning'), __('Receive your digital membership card, take lessons and quizzes, and join events.')],
            ] as [$step, $text])
                <li class="relative">
                    <span class="grid size-14 place-items-center rounded-full border-4 border-white bg-ink text-2xl font-black text-brand shadow-sm" aria-hidden="true">{{ $loop->iteration }}</span>
                    <h3 class="mt-5 text-xl font-black">
                        <span class="sr-only">{{ __('Step :number', ['number' => $loop->iteration]) }}: </span>{{ $step }}
                    </h3>
                    <p class="mt-2 max-w-[36ch] text-lg leading-relaxed text-slate">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
        </div>
    </section>

    {{-- Latest lessons --}}
    <section class="mx-auto max-w-7xl px-4 pt-20 sm:px-6" aria-labelledby="lessons-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="lessons-heading" class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Latest lessons') }}</h2>
            <a href="{{ Locales::route('learn.index') }}" class="link">{{ __('All lessons') }}</a>
        </div>
        @if ($lessons->isEmpty())
            <p class="mt-6 max-w-[60ch] rounded-2xl bg-mist p-6 text-lg text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
        @else
            <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lessons as $lesson)
                    @include('learn.partials.card', ['lesson' => $lesson])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Upcoming events --}}
    <section class="mx-auto max-w-7xl px-4 pt-20 sm:px-6" aria-labelledby="events-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="events-heading" class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Upcoming events') }}</h2>
            <a href="{{ Locales::route('events.index') }}" class="link">{{ __('All events') }}</a>
        </div>
        @if ($events->isEmpty())
            <p class="mt-6 max-w-[60ch] rounded-2xl bg-mist p-6 text-lg text-slate">{{ __('No events are scheduled yet. Check back soon or join to be notified.') }}</p>
        @else
            <div class="mt-8 grid gap-4">
                @foreach ($events as $event)
                    @include('events.partials.row', ['event' => $event])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Patrons and schools --}}
    <section class="mx-auto max-w-7xl px-4 pt-20 sm:px-6">
        <div class="relative overflow-hidden rounded-3xl border-t-8 border-brand bg-ink p-8 text-white md:border-t-0 md:p-12">
            <div class="absolute -right-16 -top-16 hidden size-64 rounded-full bg-brand md:block" aria-hidden="true"></div>
            <div class="absolute -right-4 top-20 hidden size-24 rounded-full border-[6px] border-brand md:block" aria-hidden="true"></div>
            <div class="relative max-w-2xl">
                <h2 class="text-3xl font-black">{{ __('Is your school starting a Tax Club?') }}</h2>
                <p class="mt-3 text-lg text-white/80">{{ __('Patrons can register their institution with the TRA Tax Club coordinator in their region, then verify student members online.') }}</p>
                <a href="{{ Locales::route('about') }}#patrons" class="btn-brand mt-7">{{ __('Information for patrons') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
