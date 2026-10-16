@php use App\Support\Locales; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ($title ?? null) ? $title.' | ' : '' }}TRA Tax Club</title>
    @include('partials.head-assets')
</head>
<body class="min-h-screen bg-mist">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        {{-- Brand panel (desktop) / brand strip (mobile) --}}
        <aside class="sun-hatch flex flex-col border-b-4 border-ink px-4 py-5 sm:px-8 lg:border-b-0 lg:border-r-4 lg:px-12 lg:py-10">
            <div class="flex items-center justify-between gap-4">
                @include('partials.brand')
                @foreach (Locales::SUPPORTED as $code => $label)
                    @continue($code === app()->getLocale())
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-extrabold text-brand hover:bg-ink-soft">{{ $label }}</a>
                @endforeach
            </div>

            <div class="hidden flex-1 flex-col justify-center lg:flex">
                <p class="max-w-[14ch] text-5xl font-black leading-[1.05] tracking-tight">{{ __('Taxes build our schools, roads and hospitals.') }}</p>
                <ul class="mt-10 space-y-4 text-lg font-bold text-ink-soft">
                    @foreach ([__('Free tax lessons and quizzes'), __('Points, badges and certificates'), __('National events and competitions'), __('A digital membership card')] as $benefit)
                        <li class="flex items-center gap-3">
                            <svg viewBox="0 0 20 20" class="size-7 shrink-0 rounded-full bg-ink p-1 text-brand" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            {{ $benefit }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="hidden text-sm font-bold text-ink-soft lg:block">© {{ now()->year }} Tanzania Revenue Authority</p>
        </aside>

        <main class="flex justify-center px-4 py-10 sm:px-8 lg:items-center lg:py-14">
            <div class="w-full max-w-lg rounded-3xl border-2 border-line bg-white p-6 shadow-[0_20px_50px_-30px_rgb(0_0_0/0.35)] sm:p-10">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
