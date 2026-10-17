@php use App\Support\Locales; @endphp
<footer class="mt-24 bg-ink text-white">
    <div class="h-2 bg-brand" aria-hidden="true"></div>
    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-14 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr]">
        <div class="max-w-sm">
            @include('partials.brand', ['inverse' => true])
            <p class="mt-5 leading-relaxed text-white/75">
                {{ __('A Tanzania Revenue Authority programme for students in secondary schools, colleges and universities.') }}
            </p>
        </div>
        <div>
            <h2 class="font-black text-brand">{{ __('Explore') }}</h2>
            <ul class="mt-4 space-y-3 text-white/85">
                <li><a class="hover:text-brand" href="{{ Locales::route('learn.index') }}">{{ __('Learn') }}</a></li>
                <li><a class="hover:text-brand" href="{{ Locales::route('events.index') }}">{{ __('Events') }}</a></li>
                <li><a class="hover:text-brand" href="{{ Locales::route('about') }}">{{ __('About Tax Club') }}</a></li>
                <li><a class="hover:text-brand" href="https://www.tra.go.tz" rel="noopener">tra.go.tz</a></li>
            </ul>
        </div>
        <div>
            <h2 class="font-black text-brand">{{ __('Members') }}</h2>
            <ul class="mt-4 space-y-3 text-white/85">
                <li><a class="hover:text-brand" href="{{ route('register') }}">{{ __('Join Tax Club') }}</a></li>
                <li><a class="hover:text-brand" href="{{ route('login') }}">{{ __('Sign in') }}</a></li>
                @foreach ($alternates as $altLocale => $altUrl)
                    @continue($altLocale === app()->getLocale())
                    <li><a class="hover:text-brand" href="{{ $altUrl }}" hreflang="{{ $altLocale }}" lang="{{ $altLocale }}">{{ Locales::SUPPORTED[$altLocale] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <p class="mx-auto max-w-7xl px-4 py-5 text-sm text-white/60 sm:px-6">
            © {{ now()->year }} Tanzania Revenue Authority. {{ __('Remember: ask for a receipt every time you buy.') }}
        </p>
    </div>
</footer>
