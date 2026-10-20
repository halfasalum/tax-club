<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.community.space', $post->space) }}" class="link">← {{ $post->space->name }}</a>

    <article class="mt-4 rounded-3xl border-2 border-line bg-white p-6">
        <p class="text-sm text-slate">
            <span class="font-extrabold text-ink">{{ $post->author?->publicName() }}</span>
            @if ($post->author?->institution) · {{ $post->author->institution->name }}@endif
            · {{ $post->created_at->diffForHumans() }}@if ($post->edited_at) · {{ __('edited') }}@endif
            @if ($post->status === 'held')<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold text-ink">{{ __('Waiting for a moderator') }}</span>@endif
        </p>
        @if ($editing)
            <form wire:submit="saveEdit" class="mt-3 space-y-3">
                <input wire:model="editTitle" type="text" class="field mt-0" aria-label="{{ __('Title') }}">
                <textarea wire:model="editBody" rows="5" class="field mt-0" aria-label="{{ __('Your post') }}"></textarea>
                @error('editBody')<p class="font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                <div class="flex gap-2"><button type="submit" class="btn-primary">{{ __('Save') }}</button><button type="button" wire:click="$set('editing', false)" class="btn-ghost">{{ __('Cancel') }}</button></div>
            </form>
        @else
            @if ($post->title)<h1 class="mt-2 text-2xl font-black">{{ $post->title }}</h1>@endif
            <p class="mt-2 whitespace-pre-line text-lg leading-relaxed">{{ $post->body }}</p>
        @endif
        <p class="mt-4 flex flex-wrap items-center gap-4 text-sm font-bold">
            @if ($post->user_id === auth()->id())
                <button type="button" wire:click="startEdit" class="link">{{ __('Edit') }}</button>
                <button type="button" wire:click="deletePost('{{ $post->post_id }}')" wire:confirm="{{ __('Delete this post?') }}" class="link">{{ __('Delete') }}</button>
            @else
                <livewire:safety.report-button type="post" :subject-id="$post->post_id" :key="'report-'.$post->post_id" />
                @if ($isModerator)<button type="button" wire:click="deletePost('{{ $post->post_id }}')" wire:confirm="{{ __('Remove this post as a moderator?') }}" class="link text-danger">{{ __('Remove') }}</button>@endif
            @endif
        </p>
    </article>

    <h2 class="mt-8 text-xl font-black">{{ trans_choice('{0} Comments|{1} :count comment|[2,*] :count comments', $comments->count(), ['count' => $comments->count()]) }}</h2>
    <ul class="mt-3 space-y-3">
        @foreach ($comments as $comment)
            <li wire:key="comment-{{ $comment->post_id }}" class="rounded-2xl bg-white p-4">
                <p class="text-sm text-slate">
                    <span class="font-extrabold text-ink">{{ $comment->author?->publicName() }}</span> · {{ $comment->created_at->diffForHumans() }}
                    @if ($comment->status === 'held')<span class="ml-1 rounded-full bg-brand px-2 py-0.5 text-xs font-extrabold text-ink">{{ __('Waiting for a moderator') }}</span>@endif
                </p>
                <p class="mt-1 whitespace-pre-line">{{ $comment->body }}</p>
                <p class="mt-2 flex flex-wrap gap-4 text-sm font-bold">
                    @if ($comment->user_id === auth()->id() || $isModerator)
                        <button type="button" wire:click="deletePost('{{ $comment->post_id }}')" wire:confirm="{{ __('Delete this comment?') }}" class="link">{{ __('Delete') }}</button>
                    @endif
                    @if ($comment->user_id !== auth()->id())<livewire:safety.report-button type="post" :subject-id="$comment->post_id" :key="'report-'.$comment->post_id" />@endif
                </p>
            </li>
        @endforeach
    </ul>

    @if ($statusMessage)<p role="status" class="mt-4 rounded-2xl bg-savanna px-5 py-3 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @if ($post->status === 'published' && (! $problem || $problem === __('Only moderators post here. You can comment on posts.')))
        <form wire:submit="addComment" class="mt-4 rounded-2xl border-2 border-line bg-white p-4">
            <label for="comment" class="block font-bold">{{ __('Add a comment') }}</label>
            <textarea wire:model="comment" id="comment" rows="2" maxlength="3000" @class(['field', 'field-error' => $errors->has('comment')])></textarea>
            @error('comment')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary mt-3">{{ __('Comment') }}</button>
        </form>
    @elseif ($problem)
        <p class="mt-4 rounded-2xl bg-mist p-4 font-bold">{{ $problem }}</p>
    @endif
</div>
