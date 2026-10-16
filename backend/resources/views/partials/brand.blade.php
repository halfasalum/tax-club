@php($inverse = $inverse ?? false)
<a href="{{ \App\Support\Locales::route('home') }}" class="flex items-center gap-3 rounded-lg" aria-label="TRA Tax Club — {{ __('Home') }}">
    <span @class(['grid size-12 shrink-0 place-items-center rounded-xl', 'bg-white' => $inverse])>
        <img src="{{ asset('images/tra-logo.webp') }}" alt="" width="208" height="242" class="h-10 w-auto">
    </span>
    <span class="leading-tight">
        <span @class(['block text-xl font-black tracking-tight', 'text-white' => $inverse])>Tax Club</span>
        <span @class(['block text-xs font-bold', 'text-white/70' => $inverse, 'text-slate' => ! $inverse])>{{ __('Tanzania Revenue Authority') }}</span>
    </span>
</a>
