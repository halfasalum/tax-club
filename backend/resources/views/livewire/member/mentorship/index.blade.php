@php
    $statuses = ['submitted' => __('Waiting for review'), 'approved' => __('Approved mentor'), 'rejected' => __('Not approved'), 'suspended' => __('Suspended')];
    $checks = ['not_required' => '', 'pending' => __('Background check pending (needed to mentor members under 18)'), 'cleared' => __('Background check cleared'), 'failed' => __('Background check not cleared')];
    $hasActive = $mentorships->where('status', 'active')->where('mentee_id', auth()->id())->isNotEmpty();
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Mentorship') }}</h1>
    <p class="mt-1 max-w-[65ch] text-lg text-slate">{{ __('Get guidance from Tax Club alumni and TRA staff on studies, careers and taxes.') }}</p>

    <p class="mt-6 rounded-2xl border-2 border-ink bg-brand-wash p-5 font-bold">{{ __('Stay safe: meet online through approved channels or at your Tax Club, never alone in private. There is no private chat; record each session here. Members under 18 need a guardian\'s agreement, and their patron can follow the mentorship.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('form')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    @if ($mentorships->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-2xl font-black">{{ __('Your mentorships') }}</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($mentorships as $mentorship)
                    @php($asMentor = $mentorship->mentor_id === auth()->id())
                    <li>
                        <a wire:navigate href="{{ route('member.mentorship.show', $mentorship) }}" class="block rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                            <span class="text-sm font-extrabold text-slate">{{ $asMentor ? __('You mentor') : __('Your mentor') }}</span>
                            <span class="mt-1 block text-lg font-black">{{ $asMentor ? $mentorship->mentee?->publicName() : $mentorship->mentor?->full_name }}</span>
                            <span class="mt-1 block text-sm">{{ $mentorship->status === 'active' ? __('Active since :date', ['date' => $mentorship->started_at->translatedFormat('j M Y')]) : __('Ended :date', ['date' => $mentorship->ended_at?->translatedFormat('j M Y')]) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        @if (auth()->user()->user_type !== 'staff')
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Ask for a mentor') }}</h2>
                @if ($openRequest)
                    <p class="mt-3 font-bold">{{ __('Your request is waiting for a match.') }}</p>
                    @if (auth()->user()->isMinor() && ! $openRequest->consent_confirmed_at)<p class="mt-2 text-slate">{{ __('Your patron needs to confirm that your parent or guardian agreed.') }}</p>@endif
                    <button type="button" wire:click="cancelRequest({{ $openRequest->id }})" class="link mt-3">{{ __('Cancel request') }}</button>
                @elseif ($hasActive)
                    <p class="mt-3 text-slate">{{ __('You already have a mentor.') }}</p>
                @else
                    <form wire:submit="requestMentor" class="mt-4 space-y-3">
                        <fieldset>
                            <legend class="font-bold">{{ __('What do you want help with?') }}</legend>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($areas as $value => $label)<label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="interests" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}</label>@endforeach
                            </div>
                            @error('interests')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                        </fieldset>
                        <div>
                            <label for="goals" class="block font-bold">{{ __('Your goals') }}</label>
                            <textarea wire:model="goals" id="goals" rows="3" class="field" placeholder="{{ __('For example: I want to study accounting and work at TRA.') }}"></textarea>
                            @error('goals')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                        </div>
                        <select wire:model="mode" class="field mt-0" aria-label="{{ __('How to meet') }}">
                            <option value="either">{{ __('Online or at my Tax Club') }}</option>
                            <option value="online">{{ __('Online only') }}</option>
                            <option value="in_person">{{ __('At my Tax Club') }}</option>
                        </select>
                        @if (auth()->user()->isMinor())
                            <label class="flex items-start gap-2 rounded-xl bg-mist p-3"><input type="checkbox" wire:model="guardian_consent" class="mt-1 size-4 accent-ink"> <span class="font-bold">{{ __('My parent or guardian agrees that I have a mentor') }}</span></label>
                        @endif
                        <button type="submit" class="btn-primary">{{ __('Send request') }}</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($canMentor)
            <section class="rounded-3xl border-4 border-ink bg-white p-6">
                <h2 class="text-xl font-black">{{ $profile ? __('Your mentor profile') : __('Become a mentor') }}</h2>
                @if ($profile)
                    <p class="mt-2 font-bold">{{ $statuses[$profile->status] ?? $profile->status }}</p>
                    @if ($checks[$profile->background_check] ?? '')<p class="text-sm text-slate">{{ $checks[$profile->background_check] }}</p>@endif
                    @if ($profile->review_note)<p class="mt-1 text-sm">{{ $profile->review_note }}</p>@endif
                @endif
                @if (! $profile || in_array($profile->status, ['submitted', 'rejected', 'approved'], true))
                    <form wire:submit="apply" class="mt-4 space-y-3">
                        <fieldset>
                            <legend class="font-bold">{{ __('Areas you can help with') }}</legend>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($areas as $value => $label)<label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="expertise" value="{{ $value }}" class="size-4 accent-ink"> {{ $label }}</label>@endforeach
                            </div>
                            @error('expertise')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                        </fieldset>
                        <div>
                            <label for="bio" class="block font-bold">{{ __('About you as a mentor') }}</label>
                            <textarea wire:model="bio" id="bio" rows="3" class="field"></textarea>
                            @error('bio')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
                            <input wire:model="availability" type="text" class="field mt-0" placeholder="{{ __('When you are available') }}" aria-label="{{ __('When you are available') }}">
                            <input wire:model="max_mentees" type="number" min="1" max="5" class="field mt-0" aria-label="{{ __('Mentees at a time') }}" title="{{ __('Mentees at a time') }}">
                        </div>
                        <label class="flex items-start gap-2"><input type="checkbox" wire:model="mentors_minors" class="mt-1 size-4 accent-ink"> <span class="font-semibold">{{ __('I am willing to mentor members under 18 (needs a background check)') }}</span></label>
                        <label class="flex items-start gap-2 rounded-xl bg-mist p-3"><input type="checkbox" wire:model="declaration" class="mt-1 size-4 accent-ink"> <span class="font-bold">{{ __('I accept the mentor code of conduct: I will keep all contact professional, never ask for personal contact details or private meetings, record every session, and report any concern.') }}</span></label>
                        <button type="submit" class="btn-primary">{{ $profile ? __('Update') : __('Apply') }}</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</div>
