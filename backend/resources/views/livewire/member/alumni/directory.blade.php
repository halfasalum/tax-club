<div>
    <a wire:navigate href="{{ route('member.alumni') }}" class="link">← {{ __('Alumni') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Alumni directory') }}</h1>
    <p class="mt-1 max-w-[65ch] text-lg text-slate">{{ __('Alumni who chose to be listed. Ask to connect; contact details are shared only when they accept.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('message')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    @if ($requests->isNotEmpty())
        <section class="mt-6 rounded-3xl border-4 border-ink bg-white p-6">
            <h2 class="text-xl font-black">{{ __('Connection requests') }}</h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($requests as $request)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span><span class="font-bold">{{ $request->full_name }}</span>@if ($request->message)<span class="block text-slate">“{{ $request->message }}”</span>@endif</span>
                        <span class="flex gap-2"><button type="button" wire:click="respond({{ $request->id }}, true)" class="btn-primary">{{ __('Accept') }}</button><button type="button" wire:click="respond({{ $request->id }}, false)" class="btn-ghost">{{ __('Decline') }}</button></span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by name, field, work or skill') }}" class="field mt-6 md:max-w-md" aria-label="{{ __('Search') }}">

    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($profiles as $profile)
            @php($link = $connection($profile->user))
            @php($details = $contact($profile->user))
            <li wire:key="alum-{{ $profile->user_id }}" class="flex flex-col rounded-2xl border-2 border-line bg-white p-5">
                <span class="text-lg font-black">{{ $profile->user?->full_name }}</span>
                <span class="font-bold text-ink-soft">{{ collect([$profile->occupation, $profile->organization])->filter()->implode(' · ') }}</span>
                <span class="mt-1 text-sm text-slate">{{ collect([$profile->field, $profile->user?->institution?->name, $profile->graduation_year])->filter()->implode(' · ') }}</span>
                @if ($profile->bio)<p class="mt-2 text-sm">{{ \Illuminate\Support\Str::limit($profile->bio, 150) }}</p>@endif
                @if ($profile->open_to_mentoring)<span class="mt-2 w-fit rounded-full bg-brand px-2.5 py-0.5 text-xs font-extrabold">{{ __('Open to mentoring') }}</span>@endif
                <div class="mt-auto pt-4">
                    @if ($details)
                        <p class="rounded-xl bg-mist p-3 text-sm">@if ($details['phone']){{ $details['phone'] }}<br>@endif @if ($details['email']){{ $details['email'] }}<br>@endif @if ($details['linkedin'])<a href="{{ $details['linkedin'] }}" class="link" target="_blank" rel="noopener">LinkedIn</a>@endif</p>
                    @elseif ($link)
                        <p class="text-sm font-bold text-slate">{{ $link->status === 'pending' ? __('Request waiting') : __('Not connected') }}</p>
                    @elseif ($connecting === $profile->user_id)
                        <form wire:submit="connect" class="space-y-2">
                            <textarea wire:model="message" rows="2" class="field mt-0" placeholder="{{ __('Say who you are and why you want to connect') }}" aria-label="{{ __('Message') }}"></textarea>
                            <div class="flex gap-2"><button type="submit" class="btn-primary">{{ __('Send') }}</button><button type="button" wire:click="$set('connecting', null)" class="btn-ghost">{{ __('Cancel') }}</button></div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <button type="button" wire:click="$set('connecting', '{{ $profile->user_id }}')" class="btn-ghost">{{ __('Connect') }}</button>
                            <livewire:safety.report-button type="alumni_profile" :subject-id="$profile->user_id" :key="'rep-'.$profile->user_id" />
                        </div>
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('No alumni match.') }}</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $profiles->links() }}</div>
</div>
