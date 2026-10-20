<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.alumni') }}" class="link">← {{ __('Alumni') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight">{{ __('My alumni profile') }}</h1>
    <p class="mt-1 text-lg text-slate">{{ __('Members aged 18 and over can find you in the directory if you choose. Your phone and email are shared only with people whose connection you accept.') }}</p>

    @if ($saved)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ __('Saved.') }}</p>@endif

    <form wire:submit="save" class="mt-6 space-y-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="occupation" class="block font-bold">{{ __('What you do') }}</label><input wire:model="occupation" id="occupation" type="text" placeholder="{{ __('For example: Tax consultant') }}" class="field"></div>
            <div><label for="organization" class="block font-bold">{{ __('Where') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label><input wire:model="organization" id="organization" type="text" class="field"></div>
            <div><label for="field" class="block font-bold">{{ __('Field') }}</label><input wire:model="field" id="field" type="text" placeholder="{{ __('Accounting, law, ICT…') }}" class="field"></div>
            <div><label for="graduation_year" class="block font-bold">{{ __('Year you finished your studies') }}</label><input wire:model="graduation_year" id="graduation_year" type="number" class="field"></div>
        </div>
        <div><label for="bio" class="block font-bold">{{ __('About you') }}</label><textarea wire:model="bio" id="bio" rows="4" class="field"></textarea>@error('bio')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror</div>
        <div><label for="skills" class="block font-bold">{{ __('Skills you can share') }} <span class="font-semibold text-slate">({{ __('separate with commas') }})</span></label><input wire:model="skills" id="skills" type="text" class="field"></div>
        <div><label for="linkedin_url" class="block font-bold">LinkedIn <span class="font-semibold text-slate">({{ __('optional') }})</span></label><input wire:model="linkedin_url" id="linkedin_url" type="url" class="field">@error('linkedin_url')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror</div>
        <label class="flex items-start gap-3 rounded-xl bg-mist p-4"><input type="checkbox" wire:model="show_in_directory" class="mt-1 size-5 accent-ink"><span><span class="font-extrabold">{{ __('List me in the alumni directory') }}</span><span class="block text-slate">{{ __('Shows your name, field, work and institution.') }}</span></span></label>
        <label class="flex items-start gap-3 rounded-xl bg-mist p-4"><input type="checkbox" wire:model="open_to_mentoring" class="mt-1 size-5 accent-ink"><span><span class="font-extrabold">{{ __('I would like to mentor students') }}</span><span class="block text-slate">{{ __('Apply on the Mentorship page; TRA checks every mentor.') }}</span></span></label>
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
    </form>
</div>
