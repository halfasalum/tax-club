@php
    use App\Support\MembershipCard;

    $categories = [
        'secondary' => __('Secondary school'),
        'college' => __('College'),
        'university' => __('University'),
        'alumni' => __('Alumni'),
    ];
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight">{{ __('Check a membership card') }}</h1>

    @if ($outcome)
        @php($result = $outcome['result'])
        <div @class([
            'mt-6 rounded-2xl border-4 p-6',
            'border-savanna bg-[#ecfdf3]' => $result === MembershipCard::RESULT_VALID,
            'border-danger bg-[#fef3f2]' => $result !== MembershipCard::RESULT_VALID,
        ]) role="status" aria-live="polite">
            <p @class(['flex items-center gap-3 text-2xl font-black', 'text-savanna' => $result === MembershipCard::RESULT_VALID, 'text-danger' => $result !== MembershipCard::RESULT_VALID])>
                @if ($result === MembershipCard::RESULT_VALID)
                    <svg viewBox="0 0 20 20" class="size-9 shrink-0 rounded-full bg-savanna p-1.5 text-white" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    {{ __('Valid membership') }}
                @elseif ($result === MembershipCard::RESULT_NOT_VALID)
                    <svg viewBox="0 0 20 20" class="size-9 shrink-0 rounded-full bg-danger p-1.5 text-white" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>
                    {{ __('Membership not valid') }}
                @else
                    <svg viewBox="0 0 20 20" class="size-9 shrink-0 rounded-full bg-danger p-1.5 text-white" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>
                    {{ __('Card not recognised') }}
                @endif
            </p>

            @if ($outcome['display_name'])
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-extrabold text-slate">{{ __('Member') }}</dt>
                        <dd class="text-xl font-black">{{ $outcome['details']['full_name'] ?? $outcome['display_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-extrabold text-slate">{{ __('Status') }}</dt>
                        <dd class="text-xl font-black">{{ $outcome['status_label'] }}</dd>
                    </div>
                    @if ($details = $outcome['details'])
                        <div>
                            <dt class="text-sm font-extrabold text-slate">{{ __('Membership no.') }}</dt>
                            <dd class="whitespace-nowrap font-receipt text-lg font-semibold">{{ $details['membership_id'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-extrabold text-slate">{{ __('Category') }}</dt>
                            <dd class="text-lg font-black">
                                {{ $categories[$details['user_type']] ?? $details['user_type'] }}
                                @if ($details['is_minor'])<span class="ml-1 rounded-full bg-ink px-2 py-0.5 text-xs font-black text-brand">{{ __('Under 18') }}</span>@endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-extrabold text-slate">{{ __('Institution') }}</dt>
                            <dd class="text-lg font-black">{{ $details['institution'] ?? '—' }}@if ($details['region'])<span class="font-bold text-slate">, {{ $details['region'] }}</span>@endif</dd>
                        </div>
                        @if ($details['member_since'])
                            <div>
                                <dt class="text-sm font-extrabold text-slate">{{ __('Member since') }}</dt>
                                <dd class="text-lg font-black">{{ $details['member_since'] }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>
            @else
                <p class="mt-3 text-lg">{{ __('No Tax Club member matches this card. The card may have been replaced, or the number may be wrong.') }}</p>
            @endif
        </div>

        @if ($outcome['display_name'] && ! $outcome['details'])
            <p class="mt-4 text-slate">{{ __('TRA officers can sign in to see the member’s full details.') }}</p>
        @endif

        <button type="button" wire:click="startOver" class="btn-primary mt-8 w-full text-lg">{{ __('Check another card') }}</button>
    @else
        <p class="mt-2 text-lg text-slate">{{ __('Scan the QR code on a Tax Club card with your phone camera, or type the membership number below.') }}</p>

        <form wire:submit="check" class="mt-8 space-y-6" novalidate>
            <div>
                <label for="number" class="block font-extrabold">{{ __('Membership number') }}</label>
                <input wire:model="number" id="number" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="TC-26-DSM-000123-4"
                       @class(['field font-receipt text-lg uppercase', 'field-error' => $errors->has('number')])
                       @error('number') aria-invalid="true" aria-describedby="number-error" @enderror>
                @error('number')<p id="number-error" class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full text-lg" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="check">{{ __('Check card') }}</span>
                <span wire:loading wire:target="check">{{ __('Checking…') }}</span>
            </button>
        </form>
    @endif
</div>
