@php use App\Support\Locales; @endphp
<x-layouts.public>
    {{-- Hero --}}
    <section class="overflow-hidden border-b border-line bg-white">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 sm:px-6 md:grid-cols-[1.15fr_1fr] md:py-20">
            <div>
                <h1 class="max-w-[16ch] text-[2.6rem] font-extrabold leading-[1.05] tracking-[-0.02em] sm:text-[3.4rem]">
                    {{ __('Taxes build our schools, roads and hospitals.') }}
                </h1>
                <p class="mt-6 max-w-[56ch] text-lg leading-relaxed text-slate">
                    {{ __('Tax Club brings students in secondary schools, colleges and universities together to learn how taxation works, test what they know and take part in national activities run by TRA.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="rounded-md bg-flag px-6 py-3 font-bold text-ink hover:bg-[#ffc81f]">{{ __('Join Tax Club') }}</a>
                    <a href="{{ Locales::route('learn.index') }}" class="rounded-md border border-ink/20 px-6 py-3 font-semibold text-ink hover:border-ink">{{ __('Browse lessons') }}</a>
                </div>
                @if ($memberCount > 0 && $institutionCount > 0)
                    <p class="mt-8 text-sm text-slate">
                        {{ trans_choice(':count member|:count members', $memberCount, ['count' => number_format($memberCount)]) }},
                        {{ trans_choice(':count institution|:count institutions', $institutionCount, ['count' => number_format($institutionCount)]) }}
                    </p>
                @endif
            </div>

            <div class="relative rounded-xl bg-ink px-6 pb-10 pt-5 sm:px-10">
                <div class="mx-auto mb-[-2px] h-2.5 max-w-[23rem] rounded-full bg-black/40" aria-hidden="true"></div>
                <x-receipt :items="[
                    __('Tax lessons'),
                    __('Quizzes and badges'),
                    __('National events'),
                    __('Digital card'),
                    __('Certificates'),
                ]" />
            </div>
        </div>
    </section>

    {{-- How to join: a real sequence, so it is numbered --}}
    <section class="mx-auto max-w-6xl px-4 pt-20 sm:px-6" aria-labelledby="join-heading">
        <h2 id="join-heading" class="text-3xl font-extrabold tracking-tight">{{ __('How to join') }}</h2>
        <ol class="mt-10 grid gap-10 md:grid-cols-3">
            @foreach ([
                [__('Register'), __('Sign up on the website or the Tax Club app and choose your school, college or university.')],
                [__('Get confirmed'), __('Your Tax Club patron or TRA coordinator confirms that you are a member.')],
                [__('Start learning'), __('Receive your digital membership card, take lessons and quizzes, and join events.')],
            ] as [$step, $text])
                <li class="border-t-[3px] border-ink pt-5">
                    <span class="text-sm font-semibold text-slate">{{ __('Step :number', ['number' => $loop->iteration]) }}</span>
                    <h3 class="mt-1 text-xl font-bold">{{ $step }}</h3>
                    <p class="mt-2 max-w-[38ch] leading-relaxed text-slate">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Latest lessons --}}
    <section class="mx-auto max-w-6xl px-4 pt-20 sm:px-6" aria-labelledby="lessons-heading">
        <div class="flex items-end justify-between gap-4">
            <h2 id="lessons-heading" class="text-3xl font-extrabold tracking-tight">{{ __('Latest lessons') }}</h2>
            <a href="{{ Locales::route('learn.index') }}" class="font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4">{{ __('All lessons') }}</a>
        </div>
        @if ($lessons->isEmpty())
            <p class="mt-6 max-w-[60ch] text-slate">{{ __('Lessons will appear here as soon as TRA publishes them.') }}</p>
        @else
            <div class="mt-8 divide-y divide-line border-y border-line">
                @foreach ($lessons as $lesson)
                    @include('learn.partials.row', ['lesson' => $lesson])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Upcoming events --}}
    <section class="mx-auto max-w-6xl px-4 pt-20 sm:px-6" aria-labelledby="events-heading">
        <div class="flex items-end justify-between gap-4">
            <h2 id="events-heading" class="text-3xl font-extrabold tracking-tight">{{ __('Upcoming events') }}</h2>
            <a href="{{ Locales::route('events.index') }}" class="font-semibold text-ink underline decoration-flag decoration-2 underline-offset-4">{{ __('All events') }}</a>
        </div>
        @if ($events->isEmpty())
            <p class="mt-6 max-w-[60ch] text-slate">{{ __('No events are scheduled yet. Check back soon or join to be notified.') }}</p>
        @else
            <div class="mt-8 divide-y divide-line border-y border-line">
                @foreach ($events as $event)
                    @include('events.partials.row', ['event' => $event])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Patrons and schools --}}
    <section class="mx-auto max-w-6xl px-4 pt-20 sm:px-6">
        <div class="grid gap-6 rounded-lg bg-ink p-8 text-white md:grid-cols-[1fr_auto] md:items-center md:p-10">
            <div>
                <h2 class="text-2xl font-extrabold">{{ __('Is your school starting a Tax Club?') }}</h2>
                <p class="mt-2 max-w-[60ch] text-white/80">{{ __('Patrons can register their institution with the TRA Tax Club coordinator in their region, then verify student members online.') }}</p>
            </div>
            <a href="{{ Locales::route('about') }}#patrons" class="justify-self-start rounded-md bg-white px-5 py-3 font-semibold text-ink hover:bg-paper">{{ __('Information for patrons') }}</a>
        </div>
    </section>
</x-layouts.public>
