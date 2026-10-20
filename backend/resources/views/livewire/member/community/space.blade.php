<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.community') }}" class="link">← {{ __('Community') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight">{{ $space->name }}</h1>
    @if ($space->description)<p class="mt-1 text-lg text-slate">{{ $space->description }}</p>@endif

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    @if ($problem)
        <p class="mt-6 rounded-2xl bg-mist p-5 font-bold">{{ $problem }}</p>
    @else
        <form wire:submit="publish" class="mt-6 space-y-3 rounded-3xl border-2 border-line bg-white p-5">
            <label for="title" class="sr-only">{{ __('Title') }}</label>
            <input wire:model="title" id="title" type="text" maxlength="200" placeholder="{{ __('Title (optional)') }}" class="field mt-0">
            <label for="body" class="sr-only">{{ __('Your post') }}</label>
            <textarea wire:model="body" id="body" rows="3" maxlength="5000" placeholder="{{ __('Share an idea or ask the club something…') }}" @class(['field mt-0', 'field-error' => $errors->has('body')])></textarea>
            @error('body')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate">{{ __('Do not share phone numbers or other contact details.') }}</p>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Post') }}</button>
            </div>
        </form>
    @endif

    <ul class="mt-6 space-y-4" wire:loading.class="opacity-60">
        @forelse ($this->posts as $post)
            <li wire:key="post-{{ $post->post_id }}" class="rounded-2xl border-2 border-line bg-white p-5">
                <p class="text-sm text-slate">
                    <span class="font-extrabold text-ink">{{ $post->author?->publicName() }}</span>
                    @if ($post->author?->institution) · {{ $post->author->institution->name }}@endif
                    · {{ $post->created_at->diffForHumans() }}
                    @if ($post->status === 'held')<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold text-ink">{{ __('Waiting for a moderator') }}</span>@endif
                </p>
                <a wire:navigate href="{{ route('member.community.post', $post) }}" class="mt-2 block">
                    @if ($post->title)<span class="block text-lg font-black hover:underline">{{ $post->title }}</span>@endif
                    <span class="mt-1 block whitespace-pre-line">{{ \Illuminate\Support\Str::limit($post->body, 400) }}</span>
                </a>
                <p class="mt-3 flex flex-wrap items-center gap-4 text-sm font-bold">
                    <a wire:navigate href="{{ route('member.community.post', $post) }}" class="link">{{ trans_choice('{0} Comment|{1} :count comment|[2,*] :count comments', $post->comments_count, ['count' => $post->comments_count]) }}</a>
                    @if ($post->user_id !== auth()->id())<livewire:safety.report-button type="post" :subject-id="$post->post_id" :key="'report-'.$post->post_id" />@endif
                </p>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-8 text-center font-bold text-slate">{{ __('No posts yet. Start the conversation!') }}</li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $this->posts->links() }}</div>
</div>
