@php
    use App\Models\Content;
    use App\Models\ContentVersion;
    use Illuminate\Support\Facades\Storage;

    $types = ['article' => __('Article'), 'video' => __('Video'), 'infographic' => __('Infographic'), 'document' => __('Document')];
    $levels = ['beginner' => __('Beginner'), 'intermediate' => __('Intermediate'), 'advanced' => __('Advanced')];
    $audiences = ['primary' => __('Primary school'), 'secondary' => __('Secondary school'), 'college' => __('College'), 'university' => __('University'), 'alumni' => __('Alumni')];
    $actions = [
        'created' => __('Created'), 'revision_started' => __('New revision started'), 'back_to_draft' => __('Back to draft'),
        'submitted' => __('Sent for review'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'), 'rejected' => __('Rejected'),
        'published' => __('Published'), 'scheduled' => __('Scheduled'), 'went_live' => __('Went live'), 'expired' => __('Publication ended'),
        'unpublished' => __('Unpublished'), 'archived' => __('Archived'),
    ];
    $errorText = 'mt-2 font-semibold text-danger';
    $previewContent = new Content($shown);
    $previewContent->media_path = $shown['media_path'] ?? null;
@endphp
<div>
    <a wire:navigate href="{{ route('admin.learning.index') }}" class="link">← {{ __('Learning content') }}</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $content->code }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $shown['title'] ?? $content->title }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2">
                @if ($content->current_version)
                    <x-editorial-status :status="$content->isLive() ? 'published' : $content->publication_status" />
                    <span class="font-bold text-slate">{{ __('Live version :version', ['version' => $content->current_version]) }}</span>
                    @if ($content->publication_status === Content::SCHEDULED && $content->publish_at?->isFuture())
                        <span class="text-slate">· {{ __('from :date', ['date' => $content->publish_at->translatedFormat('j M Y H:i')]) }}</span>
                    @endif
                    @if ($content->expires_at)
                        <span class="text-slate">· {{ __('until :date', ['date' => $content->expires_at->translatedFormat('j M Y')]) }}</span>
                    @endif
                @else
                    <x-editorial-status status="not_published" />
                @endif
                @if ($revision)
                    <span class="text-slate">·</span>
                    <x-editorial-status :status="$revision->status" />
                    <span class="font-bold text-slate">{{ __('Version :version', ['version' => $revision->version]) }}</span>
                @endif
            </p>
            @if ($content->review_due_at && $content->isLive() && $content->review_due_at->lte(now()->addMonth()))
                <p class="mt-3 rounded-xl bg-brand-wash px-4 py-2 font-bold">{{ __('Due for review on :date: check that the tax information is still correct.', ['date' => $content->review_due_at->translatedFormat('j M Y')]) }}</p>
            @endif
        </div>
        @if ($can['edit'])
            <a wire:navigate href="{{ route('admin.learning.edit', $content) }}" class="btn-ghost">{{ $revision ? __('Edit') : __('Start a new version') }}</a>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            @if ($revision && $content->current_version)
                <div class="flex flex-wrap items-center gap-2 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist">
                    <button type="button" role="tab" wire:click="$set('preview', 'working')" aria-selected="{{ $preview === 'working' ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $preview === 'working'])>{{ __('Version :version (working)', ['version' => $revision->version]) }}</button>
                    <button type="button" role="tab" wire:click="$set('preview', 'live')" aria-selected="{{ $preview === 'live' ? 'true' : 'false' }}" @class(['rounded-xl px-4 py-2 font-extrabold', 'bg-ink text-brand' => $preview === 'live'])>{{ __('Version :version (live)', ['version' => $content->current_version]) }}</button>
                    @if ($changed)
                        <span class="px-2 text-sm font-bold text-slate">{{ __('Changed: :fields', ['fields' => collect($changed)->map(fn ($field) => str_replace('_', ' ', $field))->implode(', ')]) }}</span>
                    @endif
                </div>
            @endif

            <article class="rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
                <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div><dt class="inline font-extrabold">{{ __('Type') }}:</dt> <dd class="inline">{{ $types[$shown['content_type'] ?? 'article'] ?? '' }}</dd></div>
                    <div><dt class="inline font-extrabold">{{ __('Category') }}:</dt> <dd class="inline">{{ $shownCategory?->name() ?? '—' }}</dd></div>
                    <div><dt class="inline font-extrabold">{{ __('Level') }}:</dt> <dd class="inline">{{ $levels[$shown['level'] ?? 'beginner'] ?? '' }}</dd></div>
                    <div><dt class="inline font-extrabold">{{ __('For') }}:</dt> <dd class="inline">{{ empty($shown['audience']) ? __('Everyone') : collect($shown['audience'])->map(fn ($value) => $audiences[$value] ?? $value)->implode(', ') }}</dd></div>
                    <div><dt class="inline font-extrabold">{{ __('Public website') }}:</dt> <dd class="inline">{{ ($shown['is_public'] ?? false) ? __('Yes') : __('No') }}</dd></div>
                    @if (! empty($shown['estimated_minutes']))<div><dt class="inline font-extrabold">{{ __('Minutes') }}:</dt> <dd class="inline">{{ $shown['estimated_minutes'] }}</dd></div>@endif
                </dl>
                @if (! empty($shown['summary']))<p class="mt-4 text-lg text-slate">{{ $shown['summary'] }}</p>@endif
                @if (! empty($shown['objectives']))
                    <div class="mt-4 rounded-2xl bg-brand-wash p-4">
                        <p class="font-extrabold">{{ __('What members will learn') }}</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach (preg_split('/\R/', $shown['objectives']) as $objective)
                                @if (trim($objective) !== '')<li>{{ $objective }}</li>@endif
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="mt-6">
                    @include('learn.partials.media', ['lesson' => $previewContent])
                </div>
                <div class="prose prose-lg mt-6 max-w-none">{!! \App\Support\HtmlSanitizer::clean($shown['body_text'] ?? '') !!}</div>
                @if (! empty($shown['keywords']))<p class="mt-6 text-sm text-slate">{{ __('Keywords') }}: {{ $shown['keywords'] }}</p>@endif
            </article>
        </div>

        <aside class="space-y-6">
            @if ($revision && in_array($revision->status, ContentVersion::EDITABLE, true) && $can['edit'])
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for review?') }}</h2>
                    @if ($revision->review_note)
                        <p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $revision->review_note }}</p>
                    @endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full">{{ __('Send for review') }}</button>
                </section>
            @endif

            @if ($revision?->status === ContentVersion::IN_REVIEW && $can['review'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Review') }}</h2>
                    <p class="mt-1 text-sm text-slate">{{ __('Sent by :name on :date', ['name' => $revision->submitter?->full_name ?? '—', 'date' => $revision->submitted_at?->translatedFormat('j M Y H:i')]) }}</p>
                    @if ($revision->change_note)<p class="mt-2 rounded-xl bg-mist p-3"><span class="font-extrabold">{{ __('What changed') }}:</span> {{ $revision->change_note }}</p>@endif
                    @if ($ownSubmission)
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the author') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" wire:confirm="{{ __('Reject this version?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($can['publish'] && ($revision?->status === ContentVersion::APPROVED || ($content->current_version && ! $content->isLive())))
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Publish') }}</h2>
                    <p class="mt-1 text-slate">{{ $revision?->status === ContentVersion::APPROVED ? __('Version :version is approved.', ['version' => $revision->version]) : __('Put version :version back online.', ['version' => $content->current_version]) }}</p>
                    <form wire:submit="publish" class="mt-4 space-y-3">
                        <div>
                            <label for="publishAt" class="block font-bold">{{ __('Publish from') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                            <input wire:model="publishAt" id="publishAt" type="datetime-local" class="field">
                        </div>
                        <div>
                            <label for="expiresAt" class="block font-bold">{{ __('Take down on') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                            <input wire:model="expiresAt" id="expiresAt" type="datetime-local" class="field">
                        </div>
                        <button type="submit" class="btn-brand w-full">{{ __('Publish') }}</button>
                    </form>
                </section>
            @endif

            @if ($can['publish'] && $content->current_version && in_array($content->publication_status, [Content::PUBLISHED, Content::SCHEDULED, Content::UNPUBLISHED], true))
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Take offline') }}</h2>
                    <label for="offlineReason" class="mt-3 block font-bold">{{ __('Reason') }}</label>
                    <textarea wire:model="offlineReason" id="offlineReason" rows="2" class="field"></textarea>
                    <div class="mt-3 grid gap-2">
                        @if ($content->publication_status !== Content::UNPUBLISHED)
                            <button type="button" wire:click="unpublish" class="btn-ghost">{{ __('Unpublish') }}</button>
                        @endif
                        <button type="button" wire:click="archive" wire:confirm="{{ __('Archive this lesson?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Archive') }}</button>
                    </div>
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Versions') }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($versions as $version)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-black">v{{ $version->version }}</span>
                            <x-editorial-status :status="$version->status === 'superseded' ? 'archived' : $version->status" />
                            <span class="text-sm text-slate">{{ ($version->published_at ?? $version->updated_at)?->translatedFormat('j M Y') }}</span>
                            @if ($version->change_note)<span class="w-full text-sm text-slate">{{ $version->change_note }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('History') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @foreach ($events as $event)
                        <li>
                            <p class="font-extrabold">{{ $actions[$event->action] ?? $event->action }} @if ($event->version)<span class="font-semibold text-slate">v{{ $event->version }}</span>@endif</p>
                            <p class="text-sm text-slate">{{ $event->user?->full_name ?? __('System') }} · {{ $event->created_at->translatedFormat('j M Y H:i') }}</p>
                            @if ($event->note)<p class="text-sm">{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</div>
