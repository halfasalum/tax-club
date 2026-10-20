@php
    use App\Models\Opportunity;
    use App\Support\Audience;
    use App\Support\OpportunityBoard;
    use App\Support\Review;

    $actions = [
        'created' => __('Created'), 'submitted' => __('Sent for review'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'),
        'rejected' => __('Rejected'), 'published' => __('Published'), 'scheduled' => __('Scheduled'), 'went_live' => __('Went live'),
        'edited' => __('Details changed'), 'back_to_draft' => __('Back to draft'), 'closed' => __('Closed'), 'deadline_passed' => __('Closed at the deadline'),
        'withdrawn' => __('Withdrawn'), 'archived' => __('Archived'), 'reopened' => __('Reopened'),
    ];
    $methods = ['link' => __('Online application link'), 'email' => __('By email'), 'in_person' => __('In person or other instructions')];
    $live = in_array($opportunity->publication_status, [Opportunity::PUBLISHED, Opportunity::SCHEDULED, Opportunity::CLOSED], true);
@endphp
<div>
    <a wire:navigate href="{{ route('admin.opportunities.index') }}" class="link">← {{ __('Opportunities') }}</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $opportunity->code }} · {{ OpportunityBoard::typeLabels()[$opportunity->type] ?? $opportunity->type }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $opportunity->title }}</h1>
            <p class="mt-1 text-lg font-bold text-ink-soft">{{ $opportunity->organization }}</p>
            <p class="mt-2 flex flex-wrap items-center gap-2">
                <x-editorial-status :status="$opportunity->editorial_status" />
                <x-editorial-status :status="$opportunity->displayStatus()" />
                @if ($opportunity->publish_at && $opportunity->publication_status === Opportunity::SCHEDULED)<span class="text-slate">{{ __('goes live :date', ['date' => $opportunity->publish_at->translatedFormat('j M Y H:i')]) }}</span>@endif
            </p>
        </div>
        @if ($can['edit'] && ! in_array($opportunity->publication_status, [Opportunity::WITHDRAWN, Opportunity::ARCHIVED], true))
            <a wire:navigate href="{{ route('admin.opportunities.edit', $opportunity) }}" class="btn-ghost">{{ __('Edit') }}</a>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    @foreach ([
                        [__('Deadline'), $opportunity->deadline?->translatedFormat('l j M Y') ?? __('Open until filled')],
                        [__('Location'), trim($opportunity->location.($opportunity->region ? ', '.$opportunity->region : '')) ?: '—'],
                        [__('Who sees it'), Audience::describe($opportunity->audience)],
                        [__('Application channel'), $methods[$opportunity->apply_method] ?? $opportunity->apply_method],
                    ] as [$label, $value])
                        <div><dt class="inline font-extrabold">{{ $label }}:</dt> <dd class="inline">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($opportunity->applyTarget())<p class="mt-3 break-all"><a href="{{ $opportunity->applyTarget() }}" class="link" target="_blank" rel="noopener">{{ $opportunity->apply_url ?? $opportunity->apply_email }}</a></p>@endif
                <div class="mt-4 max-w-[65ch] whitespace-pre-line leading-relaxed text-ink-soft">{{ $opportunity->description }}</div>
                @if ($opportunity->eligibility)<div class="mt-4 rounded-xl bg-brand-wash p-4"><p class="font-extrabold">{{ __('Who can apply') }}</p><p class="mt-1 whitespace-pre-line">{{ $opportunity->eligibility }}</p></div>@endif
                @if ($opportunity->apply_instructions)<div class="mt-4 rounded-xl bg-mist p-4"><p class="font-extrabold">{{ __('Application instructions') }}</p><p class="mt-1 whitespace-pre-line">{{ $opportunity->apply_instructions }}</p></div>@endif
                @if ($opportunity->attachment_path)<p class="mt-4"><a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($opportunity->attachment_path) }}" class="link" target="_blank" rel="noopener">{{ $opportunity->attachment_name }}</a></p>@endif
                @if ($opportunity->status_reason)<p class="mt-4 text-slate"><span class="font-extrabold">{{ __('Reason') }}:</span> {{ $opportunity->status_reason }}</p>@endif
            </section>

            @if ($problems && $opportunity->publication_status === Opportunity::NOT_PUBLISHED)
                <ul class="list-disc space-y-1 rounded-2xl bg-[#fef3f2] p-5 pl-10 font-semibold text-danger">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            @endif

            @if ($opportunity->publication_status !== Opportunity::NOT_PUBLISHED)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Engagement') }}</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([[__('Members who viewed'), $engagement['viewers']], [__('Saves'), $engagement['saves']], [__('Saved now'), $engagement['saved_now']], [__('Opened the application'), $engagement['applies']]] as [$label, $value])
                            <div class="rounded-2xl bg-mist p-4"><dt class="text-sm font-extrabold text-slate">{{ $label }}</dt><dd class="text-2xl font-black tabular-nums">{{ number_format($value) }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if (in_array($opportunity->editorial_status, Review::EDITABLE, true) && $can['edit'] && $opportunity->publication_status === Opportunity::NOT_PUBLISHED)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for review?') }}</h2>
                    @if ($opportunity->review_note)<p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $opportunity->review_note }}</p>@endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full" @disabled($problems)>{{ __('Send for review') }}</button>
                </section>
            @endif

            @if ($opportunity->editorial_status === Review::IN_REVIEW && $can['review'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Review') }}</h2>
                    @if ($ownSubmission && \App\Support\ContentWorkflow::fourEyes())
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate">{{ __('Check that the offer is genuine, the link is safe and the audience is right for young members.') }}</p>
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the author') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" wire:confirm="{{ __('Reject this opportunity?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($opportunity->editorial_status === Review::APPROVED && $can['publish'] && in_array($opportunity->publication_status, [Opportunity::NOT_PUBLISHED, Opportunity::SCHEDULED], true))
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Publish to members') }}</h2>
                    <form wire:submit="publish" class="mt-4 space-y-3">
                        <div>
                            <label for="publishAt" class="block font-bold">{{ __('Goes live') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                            <input wire:model="publishAt" id="publishAt" type="datetime-local" class="field">
                        </div>
                        <button type="submit" class="btn-brand w-full">{{ __('Publish') }}</button>
                    </form>
                </section>
            @endif

            @if ($can['publish'] && $live)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Close, withdraw or archive') }}</h2>
                    <label for="reason" class="mt-3 block font-bold">{{ __('Reason') }}</label>
                    <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                    <div class="mt-3 grid gap-2">
                        @if ($opportunity->isOpen())<button type="button" wire:click="close" class="btn-ghost">{{ __('Close now') }}</button>@endif
                        @if ($opportunity->publication_status !== Opportunity::CLOSED)<button type="button" wire:click="withdraw" wire:confirm="{{ __('Withdraw this opportunity?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Withdraw') }}</button>@endif
                        <button type="button" wire:click="archive" class="btn-ghost">{{ __('Archive') }}</button>
                    </div>
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('History') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @foreach ($history as $step)
                        <li>
                            <p class="font-extrabold">{{ $actions[$step->action] ?? $step->action }}</p>
                            <p class="text-sm text-slate">{{ $step->user?->full_name ?? __('System') }} · {{ $step->created_at->translatedFormat('j M Y H:i') }}</p>
                            @if ($step->note)<p class="text-sm">{{ $step->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</div>
