@php use App\Support\Locales; @endphp
<footer class="mt-24 bg-ink text-white">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
        <div class="max-w-sm">
            <p class="text-lg font-extrabold">TRA Tax Club</p>
            <p class="mt-3 text-sm leading-relaxed text-white/75">
                {{ __('A Tanzania Revenue Authority programme for students in secondary schools, colleges and universities.') }}
            </p>
        </div>
        <div>
            <p class="text-sm font-semibold text-flag">{{ __('Explore') }}</p>
            <ul class="mt-3 space-y-2 text-sm text-white/80">
                <li><a class="hover:text-white" href="{{ Locales::route('learn.index') }}">{{ __('Learn') }}</a></li>
                <li><a class="hover:text-white" href="{{ Locales::route('events.index') }}">{{ __('Events') }}</a></li>
                <li><a class="hover:text-white" href="{{ Locales::route('about') }}">{{ __('About Tax Club') }}</a></li>
            </ul>
        </div>
        <div>
            <p class="text-sm font-semibold text-flag">{{ __('Members') }}</p>
            <ul class="mt-3 space-y-2 text-sm text-white/80">
                <li><a class="hover:text-white" href="{{ route('register') }}">{{ __('Join Tax Club') }}</a></li>
                <li><a class="hover:text-white" href="{{ route('login') }}">{{ __('Sign in') }}</a></li>
                @foreach ($alternates as $altLocale => $altUrl)
                    @continue($altLocale === app()->getLocale())
                    <li><a class="hover:text-white" href="{{ $altUrl }}" hreflang="{{ $altLocale }}" lang="{{ $altLocale }}">{{ Locales::SUPPORTED[$altLocale] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto max-w-6xl px-4 py-5 text-xs text-white/60 sm:px-6">
            © {{ now()->year }} Tanzania Revenue Authority. {{ __('Remember: ask for a receipt every time you buy.') }}
        </p>
    </div>
</footer>
