@php
    use App\Models\MemberStatusChange;
    use App\Support\MemberAccounts;

    $categories = ['secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $errorText = 'mt-2 font-semibold text-danger';
    $initials = collect(explode(' ', trim($member->full_name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<div class="mx-auto max-w-5xl">
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('My profile') }}</h1>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[20rem_1fr]">
        <aside class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6 text-center" aria-labelledby="photo-heading">
                <h2 id="photo-heading" class="sr-only">{{ __('Photo') }}</h2>
                @if ($member->photo_path)
                    <img src="{{ route('members.photo', ['member' => $member, 'v' => md5($member->photo_path)]) }}" alt="{{ __('Your photo') }}" width="160" height="160" class="mx-auto size-40 rounded-3xl border-4 border-ink object-cover">
                @else
                    <span class="mx-auto grid size-40 place-items-center rounded-3xl border-4 border-ink bg-brand text-5xl font-black" aria-hidden="true">{{ $initials }}</span>
                @endif
                <p class="mt-4 text-xl font-black">{{ $member->full_name }}</p>
                <p class="font-receipt text-slate">{{ $member->membership_id }}</p>

                <label class="btn-ghost mt-4 w-full cursor-pointer py-2">
                    <span wire:loading.remove wire:target="photo">{{ $member->photo_path ? __('Change photo') : __('Add a photo') }}</span>
                    <span wire:loading wire:target="photo">{{ __('Uploading…') }}</span>
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                @error('photo')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                @if ($member->photo_path)
                    <button type="button" wire:click="removePhoto" class="link mt-3">{{ __('Remove photo') }}</button>
                @endif
                <p class="mt-3 text-sm text-slate">{{ __('Use a clear photo of your face. Only you and Tax Club officers can see it.') }}</p>
            </section>

            <dl class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border-2 border-line bg-white p-4">
                    <dt class="text-sm font-extrabold text-slate">{{ __('Points') }}</dt>
                    <dd class="text-2xl font-black tabular-nums">{{ number_format($member->total_points ?? 0) }}</dd>
                </div>
                <div class="rounded-2xl border-2 border-line bg-white p-4">
                    <dt class="text-sm font-extrabold text-slate">{{ __('Quizzes taken') }}</dt>
                    <dd class="text-2xl font-black tabular-nums">{{ number_format($quizzes) }}</dd>
                </div>
            </dl>
        </aside>

        <div class="space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="membership-heading">
                <h2 id="membership-heading" class="text-2xl font-black">{{ __('Membership') }}</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        [__('Status'), MemberAccounts::statusLabel($member->account_status)],
                        [__('Category'), $categories[$member->user_type] ?? $member->user_type],
                        [__('Institution'), trim(($member->institution?->name ?? '—').', '.($member->institution?->region ?? ''), ', ')],
                        [__('Member since'), $member->membership_issued_at?->translatedFormat('j M Y') ?? '—'],
                        [__('Date of birth'), $member->date_of_birth?->translatedFormat('j M Y') ?? '—'],
                        [__('Mobile number'), $member->phone],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-sm font-extrabold text-slate">{{ $label }}</dt>
                            <dd class="font-bold">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 rounded-xl bg-mist p-3 text-slate">{{ __('Is your name, date of birth or institution wrong? Ask your Tax Club patron to correct it.') }}</p>

                @if ($history->isNotEmpty())
                    <h3 class="mt-6 font-black">{{ __('Membership history') }}</h3>
                    <ol class="mt-2 space-y-2 border-l-4 border-brand pl-4">
                        @foreach ($history as $change)
                            <li>
                                <span class="font-bold">
                                    {{ $change->field === MemberStatusChange::FIELD_CATEGORY
                                        ? __('Category: :from → :to', ['from' => $categories[$change->from_value] ?? $change->from_value, 'to' => $categories[$change->to_value] ?? $change->to_value])
                                        : MemberAccounts::statusLabel($change->to_value) }}
                                </span>
                                <span class="text-slate">{{ $change->changed_at->translatedFormat('j M Y') }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @if (MemberAccounts::canBecomeAlumni($member))
                <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="alumni-heading">
                    <h2 id="alumni-heading" class="text-2xl font-black">{{ __('Finished your studies?') }}</h2>
                    @if ($member->alumni_requested_at)
                        <p class="mt-2 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You asked to become alumni on :date. Your patron or coordinator will confirm it.', ['date' => $member->alumni_requested_at->translatedFormat('j M Y')]) }}</p>
                        <button type="button" wire:click="cancelAlumniRequest" class="link mt-3">{{ __('Cancel request') }}</button>
                    @else
                        <p class="mt-2 text-slate">{{ __('Become Tax Club alumni and stay part of the community. You keep your membership number, points and certificates.') }}</p>
                        <button type="button" wire:click="requestAlumni" wire:confirm="{{ __('Ask to become alumni?') }}" class="btn-brand mt-4">{{ __('Ask to become alumni') }}</button>
                    @endif
                    @error('alumni')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-2xl font-black">{{ __('Contact and preferences') }}</h2>
                <form wire:submit="saveDetails" class="mt-4 grid gap-4 sm:grid-cols-2" novalidate>
                    <div class="sm:col-span-2">
                        <label for="email" class="block font-extrabold">{{ __('Email') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                        <input wire:model="email" id="email" type="email" autocomplete="email" @class(['field', 'field-error' => $errors->has('email')])>
                        @error('email')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="gender" class="block font-extrabold">{{ __('Gender') }}</label>
                        <select wire:model="gender" id="gender" class="field">
                            <option value="">{{ __('Prefer not to say') }}</option>
                            <option value="female">{{ __('Female') }}</option>
                            <option value="male">{{ __('Male') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="preferred_locale" class="block font-extrabold">{{ __('Language for SMS') }}</label>
                        <select wire:model="preferred_locale" id="preferred_locale" class="field">
                            <option value="sw">Kiswahili</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary sm:col-span-2 sm:justify-self-start">{{ __('Save details') }}</button>
                </form>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="notify-heading">
                <h2 id="notify-heading" class="text-2xl font-black">{{ __('Notifications') }}</h2>
                <p class="mt-2 text-slate">{{ __('Messages about your account, membership and security always reach you. You can choose for the rest.') }}</p>
                <form wire:submit="savePreferences" class="mt-4 space-y-3">
                    @foreach (\App\Support\NotificationPreferences::categories() as $category => $info)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-line p-4">
                            <div class="min-w-0">
                                <p class="font-extrabold">{{ $info['label'] }}</p>
                                <p class="text-sm text-slate">{{ $info['description'] }}</p>
                            </div>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="preferences.{{ $category }}.inbox" class="size-4 accent-ink"> {{ __('Inbox') }}</label>
                                <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="preferences.{{ $category }}.sms" class="size-4 accent-ink"> SMS</label>
                            </div>
                        </div>
                    @endforeach
                    <button type="submit" class="btn-ghost">{{ __('Save choices') }}</button>
                </form>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="phone-heading">
                <h2 id="phone-heading" class="text-2xl font-black">{{ __('Change mobile number') }}</h2>
                @if (! $phoneCodeSent)
                    <p class="mt-2 text-slate">{{ __('We send a code to the new number to check that it is yours.') }}</p>
                    <form wire:submit="sendPhoneCode" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start" novalidate>
                        <div class="flex-1">
                            <label for="newPhone" class="sr-only">{{ __('New mobile number') }}</label>
                            <input wire:model="newPhone" id="newPhone" type="tel" inputmode="tel" placeholder="0712 345 678" @class(['field mt-0', 'field-error' => $errors->has('newPhone')])>
                            @error('newPhone')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn-ghost">{{ __('Send code') }}</button>
                    </form>
                @else
                    <p class="mt-2 font-bold">{{ __('Enter the code we sent to :phone.', ['phone' => $newPhone]) }}</p>
                    <form wire:submit="confirmPhone" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start" novalidate>
                        <div class="flex-1">
                            <label for="phoneCode" class="sr-only">{{ __('Code from the SMS') }}</label>
                            <input wire:model="phoneCode" id="phoneCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" @class(['field mt-0 font-receipt text-xl tracking-[0.3em]', 'field-error' => $errors->has('phoneCode')])>
                            @error('phoneCode')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn-primary">{{ __('Confirm number') }}</button>
                    </form>
                    <button type="button" wire:click="cancelPhoneChange" class="link mt-3">{{ __('Cancel') }}</button>
                @endif
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8" aria-labelledby="password-heading">
                <h2 id="password-heading" class="text-2xl font-black">{{ __('Change password') }}</h2>
                <form wire:submit="changePassword" class="mt-4 grid gap-4 sm:grid-cols-3" novalidate>
                    <div>
                        <label for="current_password" class="block font-extrabold">{{ __('Current password') }}</label>
                        <input wire:model="current_password" id="current_password" type="password" autocomplete="current-password" @class(['field', 'field-error' => $errors->has('current_password')])>
                        @error('current_password')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="block font-extrabold">{{ __('New password') }}</label>
                        <input wire:model="password" id="password" type="password" autocomplete="new-password" @class(['field', 'field-error' => $errors->has('password')])>
                        @error('password')<p class="{{ $errorText }}" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block font-extrabold">{{ __('Repeat new password') }}</label>
                        <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="field">
                    </div>
                    <button type="submit" class="btn-primary sm:col-span-3 sm:justify-self-start">{{ __('Change password') }}</button>
                </form>
            </section>
        </div>
    </div>
</div>
