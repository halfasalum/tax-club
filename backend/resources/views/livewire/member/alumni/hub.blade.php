@php($statuses = ['draft' => __('Draft'), 'in_review' => __('Waiting for review'), 'returned' => __('Returned for changes'), 'rejected' => __('Not published'), 'approved' => __('Published')])
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Alumni') }}</h1>
    <p class="mt-1 max-w-[65ch] text-lg text-slate">{{ __('Former Tax Club members share their journeys, give career talks and mentor today\'s students.') }}</p>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @if ($isAlumnus)
            <a wire:navigate href="{{ route('member.alumni.profile') }}" class="rounded-2xl border-4 border-ink bg-white p-5 transition hover:-translate-y-0.5">
                <span class="text-sm font-extrabold text-slate">{{ __('My alumni profile') }}</span>
                <span class="mt-1 block text-lg font-black">{{ $profile?->exists ? ($profile->show_in_directory ? __('Listed in the directory') : __('Not listed in the directory')) : __('Create your profile') }}</span>
            </a>
        @endif
        @if ($canUseDirectory)
            <a wire:navigate href="{{ route('member.alumni.directory') }}" class="rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                <span class="text-sm font-extrabold text-slate">{{ __('Alumni directory') }}</span>
                <span class="mt-1 block text-lg font-black">{{ __('Find alumni by field or work') }}</span>
                @if ($requests)<span class="mt-2 inline-block rounded-full bg-brand px-2.5 py-0.5 text-sm font-extrabold">{{ trans_choice(':count connection request|:count connection requests', $requests, ['count' => $requests]) }}</span>@endif
            </a>
        @endif
        <a wire:navigate href="{{ route('member.mentorship') }}" class="rounded-2xl bg-ink p-5 text-white transition hover:bg-ink-soft">
            <span class="text-sm font-extrabold text-brand">{{ __('Mentorship') }}</span>
            <span class="mt-1 block text-lg font-black">{{ $isAlumnus ? __('Become a mentor') : __('Ask for a mentor') }}</span>
        </a>
    </div>

    @if ($sessions->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-2xl font-black">{{ __('Career talks and alumni sessions') }}</h2>
            <div class="mt-4 grid gap-3">
                @foreach ($sessions as $event)@include('events.partials.row', ['event' => $event, 'memberLink' => true])@endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <h2 class="text-2xl font-black">{{ __('Alumni stories') }}</h2>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($stories as $story)
                <li>
                    <a wire:navigate href="{{ route('member.alumni.story', $story->slug) }}" class="flex h-full flex-col rounded-2xl border-2 border-line bg-white p-5 transition hover:border-ink">
                        <span class="text-sm font-extrabold text-slate">{{ $story->kind === 'career_tip' ? __('Career tip') : __('Story') }}</span>
                        <span class="mt-1 text-lg font-black leading-snug">{{ $story->title }}</span>
                        <span class="mt-2 flex-1 text-slate">{{ \Illuminate\Support\Str::limit($story->body, 120) }}</span>
                        <span class="mt-3 text-sm font-bold">{{ $story->author?->publicName() }}</span>
                    </a>
                </li>
            @empty
                <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate sm:col-span-2 lg:col-span-3">{{ __('Stories from alumni will appear here.') }}</li>
            @endforelse
        </ul>
    </section>

    @if ($isAlumnus)
        <section class="mt-10 grid gap-6 lg:grid-cols-[1fr_20rem]">
            <form wire:submit="submitStory" class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Share your story') }}</h2>
                <p class="text-slate">{{ __('What did Tax Club teach you? Where are you now? What would you tell students? A TRA officer checks every story before it is published.') }}</p>
                <select wire:model="storyKind" class="field mt-0 sm:w-56" aria-label="{{ __('Type') }}"><option value="story">{{ __('Story') }}</option><option value="career_tip">{{ __('Career tip') }}</option></select>
                <input wire:model="storyTitle" type="text" class="field mt-0" placeholder="{{ __('Title') }}" aria-label="{{ __('Title') }}">
                @error('storyTitle')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                <textarea wire:model="storyBody" rows="6" class="field mt-0" aria-label="{{ __('Your story') }}"></textarea>
                @error('storyBody')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                <button type="submit" class="btn-primary">{{ __('Send for review') }}</button>
            </form>
            <section class="h-fit rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Your stories') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($myStories as $story)
                        <li class="py-2"><span class="font-bold">{{ $story->title }}</span><span class="block text-sm text-slate">{{ $statuses[$story->editorial_status] ?? $story->editorial_status }}</span>@if ($story->review_note)<span class="block text-sm">{{ $story->review_note }}</span>@endif</li>
                    @empty
                        <li class="py-2 text-slate">{{ __('None yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </section>
    @endif
</div>
