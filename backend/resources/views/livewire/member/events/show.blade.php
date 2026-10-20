@php
    use App\Models\Event;
    use App\Support\EventLabels;

    $kinds = ['update' => __('Update'), 'instructions' => __('Instructions'), 'material' => __('Material')];
    $holdsPlace = $registration && in_array($registration->status, ['registered', 'attended'], true);
@endphp
<div class="mx-auto max-w-4xl">
    <a wire:navigate href="{{ route('member.events') }}" class="link">← {{ __('Events') }}</a>
    <header class="mt-4 rounded-3xl bg-ink p-6 text-white sm:p-8">
        <p class="font-extrabold text-brand">{{ EventLabels::types()[$event->type] ?? $event->type }}@if ($event->organizer) · {{ $event->organizer }}@endif</p>
        <h1 class="mt-2 text-3xl font-black leading-tight sm:text-4xl">{{ $event->title }}</h1>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-extrabold text-white/70">{{ __('When') }}</dt>
                <dd class="text-lg font-black">{{ $event->starts_at?->translatedFormat('l j F Y, H:i') }}@if ($event->ends_at) – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->format('H:i') : $event->ends_at->translatedFormat('j F, H:i') }}@endif</dd>
            </div>
            <div>
                <dt class="text-sm font-extrabold text-white/70">{{ __('Where') }}</dt>
                <dd class="text-lg font-black">{{ $event->mode === 'online' ? __('Online') : trim($event->location.($event->region ? ', '.$event->region : '')) }}@if ($event->mode === 'hybrid') · {{ __('or online') }}@endif</dd>
            </div>
        </dl>
    </header>

    @if (in_array($event->publication_status, [Event::POSTPONED, Event::CANCELLED], true))
        <p class="mt-6 rounded-2xl bg-[#fff7ed] p-5 font-bold text-[#9a3412]">
            {{ $event->publication_status === Event::CANCELLED ? __('This event was cancelled.') : __('This event was postponed. We will share the new date.') }}
            @if ($event->status_reason) {{ $event->status_reason }}@endif
        </p>
    @endif
    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('register')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                @if ($event->objective)<p class="rounded-xl bg-brand-wash p-4 font-bold">{{ $event->objective }}</p>@endif
                <div class="mt-4 max-w-[65ch] whitespace-pre-line text-lg leading-relaxed text-ink-soft">{{ $event->description }}</div>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Updates and materials') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($updates as $update)
                        <li class="py-3">
                            <p><span class="mr-2 rounded-full bg-mist px-2 py-0.5 text-xs font-extrabold">{{ $kinds[$update->kind] ?? '' }}</span><span class="font-black">{{ $update->title }}</span></p>
                            @if ($update->body)<p class="mt-1 whitespace-pre-line">{{ $update->body }}</p>@endif
                            <p class="mt-1 flex flex-wrap gap-3 text-sm">
                                @if ($update->link_url)<a href="{{ $update->link_url }}" class="link" target="_blank" rel="noopener">{{ __('Open link') }}</a>@endif
                                @if ($update->file_path)<a href="{{ route('events.material', [$event, $update]) }}" class="link">{{ __('Download :file', ['file' => $update->file_name]) }}</a>@endif
                                <span class="text-slate">{{ $update->created_at->translatedFormat('j M Y') }}</span>
                            </p>
                        </li>
                    @empty
                        <li class="py-3 text-slate">{{ __('No updates yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-3xl border-4 border-ink bg-white p-6">
                @if ($registration)
                    <p class="text-sm font-extrabold text-slate">{{ __('Your registration') }}</p>
                    <p class="mt-1 text-2xl font-black">{{ EventLabels::registrationStatuses()[$registration->status] ?? '' }}</p>
                    @if ($registration->status === 'waitlisted')
                        <p class="mt-1 font-bold">{{ __('You are number :position on the waitlist.', ['position' => $registration->waitlistPosition()]) }}</p>
                    @elseif ($registration->status === 'attended')
                        <p class="mt-1 text-slate">{{ __('Checked in :time. Thank you for coming!', ['time' => $registration->checked_in_at?->translatedFormat('j M, H:i')]) }}</p>
                    @else
                        <p class="mt-2 text-slate">{{ __('Show your membership card at the door. The QR code checks you in.') }}</p>
                        <a wire:navigate href="{{ route('member.card') }}" class="btn-brand mt-3 w-full">{{ __('Open my card') }}</a>
                    @endif
                    @if ($holdsPlace && $event->online_url && $event->mode !== 'in_person')
                        <a href="{{ $event->online_url }}" target="_blank" rel="noopener" class="btn-primary mt-3 w-full">{{ __('Join online') }}</a>
                    @endif
                    @if (in_array($registration->status, ['registered', 'waitlisted'], true) && ! $event->hasEnded())
                        <button type="button" wire:click="cancelRegistration" wire:confirm="{{ __('Cancel your registration? Your place will go to someone on the waitlist.') }}" class="link mt-4 block">{{ __('Cancel my registration') }}</button>
                    @endif
                @elseif (! $problem)
                    <p class="text-2xl font-black">{{ __('Take part') }}</p>
                    @if ($event->capacity)
                        <p class="mt-1 font-bold text-slate">{{ $event->placesLeft() > 0 ? trans_choice(':count place left|:count places left', $event->placesLeft(), ['count' => $event->placesLeft()]) : __('Full. You can join the waitlist.') }}</p>
                    @endif
                    @if ($event->registrationDeadline())
                        <p class="mt-1 text-sm text-slate">{{ __('Registration closes :date', ['date' => $event->registrationDeadline()->translatedFormat('j M Y, H:i')]) }}</p>
                    @endif
                    <form wire:submit="register" class="mt-4 space-y-3">
                        @if ($event->registration_question)
                            <div>
                                <label for="answer" class="block font-bold">{{ $event->registration_question }}</label>
                                <textarea wire:model="answer" id="answer" rows="2" class="field"></textarea>
                            </div>
                        @endif
                        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">{{ $event->capacity && $event->placesLeft() === 0 ? __('Join the waitlist') : __('Register') }}</button>
                    </form>
                @else
                    <p class="text-lg font-black">{{ $problem }}</p>
                    @if (! $event->registration_required && $event->online_url && $event->mode !== 'in_person')
                        <a href="{{ $event->online_url }}" target="_blank" rel="noopener" class="btn-primary mt-3 w-full">{{ __('Join online') }}</a>
                    @endif
                @endif
            </section>
            <a wire:navigate href="{{ route('member.feedback', ['about' => 'event:'.$event->event_id]) }}" class="link block">{{ __('Share feedback about this event') }}</a>
        </aside>
    </div>
</div>
