@php
    use App\Models\Competition;
    use App\Support\Review;

    $actions = [
        'created' => __('Created'), 'submitted' => __('Sent for review'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'), 'rejected' => __('Rejected'),
        'published' => __('Published'), 'scheduled' => __('Scheduled'), 'went_live' => __('Went live'), 'edited' => __('Details changed'), 'back_to_draft' => __('Back to draft'),
        'cancelled' => __('Cancelled'), 'prizes_set' => __('Prizes set'), 'disqualified' => __('Entry disqualified'), 'results_submitted' => __('Results sent for approval'),
        'results_returned' => __('Results returned'), 'results_published' => __('Results published'),
    ];
    $kinds = ['text' => __('Written essay'), 'link' => __('Link'), 'file' => __('File')];
    $entryStatuses = ['registered' => __('Registered'), 'submitted' => __('Entry submitted'), 'withdrawn' => __('Withdrew'), 'disqualified' => __('Disqualified')];
    $resultsOpen = $competition->results_status !== Competition::RESULTS_PUBLISHED;
@endphp
<div>
    <a wire:navigate href="{{ route('admin.competitions.index') }}" class="link">← {{ __('Competitions') }}</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $competition->code }} · {{ $competition->type === 'quiz' ? __('Quiz challenge') : $kinds[$competition->submission_kind] ?? '' }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $competition->title }}</h1>
            <p class="mt-2 flex flex-wrap gap-2">
                <x-editorial-status :status="$competition->editorial_status" />
                <x-editorial-status :status="$competition->displayStatus()" />
            </p>
        </div>
        @if ($can['edit'] && $resultsOpen && $competition->publication_status !== 'cancelled')
            <a wire:navigate href="{{ route('admin.competitions.edit', $competition) }}" class="btn-ghost">{{ __('Edit') }}</a>
        @endif
    </div>

    @if ($statusMessage)<p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>@endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    @foreach ([
                        [__('Runs'), $competition->starts_at?->translatedFormat('j M Y H:i').' – '.$competition->ends_at?->translatedFormat('j M Y H:i')],
                        [__('Registration closes'), $competition->registration_closes_at?->translatedFormat('j M Y H:i') ?? __('When it ends')],
                        [__('Who can enter'), \App\Support\Audience::describe($competition->audience)],
                        [__('Places'), $competition->max_participants ?? __('No limit')],
                        [__('Winners'), $competition->winners_count],
                        [__('Quiz'), $competition->quiz?->title ?? '—'],
                    ] as [$label, $value])
                        <div><dt class="inline font-extrabold">{{ $label }}:</dt> <dd class="inline">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <div class="mt-4 max-w-[65ch] whitespace-pre-line">{{ $competition->description }}</div>
                @if ($competition->rules)<div class="mt-4 rounded-xl bg-mist p-4"><p class="font-extrabold">{{ __('Rules') }}</p><p class="mt-1 whitespace-pre-line">{{ $competition->rules }}</p></div>@endif
            </section>

            @if ($problems && $competition->publication_status === 'not_published')
                <ul class="list-disc space-y-1 rounded-2xl bg-[#fef3f2] p-5 pl-10 font-semibold text-danger">@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-black">{{ trans_choice('{0} Entries|{1} :count entry|[2,*] :count entries', $entries->count(), ['count' => $entries->count()]) }}</h2>
                    @if ($resultsOpen && $competition->publication_status === 'published' && ($can['edit'] || $can['approveResults']))
                        <button type="button" wire:click="rank" class="btn-ghost">{{ __('Update ranking') }}</button>
                    @endif
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-left">
                        <thead class="border-b-2 border-line text-sm">
                            <tr>
                                <th scope="col" class="py-2 pr-3 font-extrabold">{{ __('Rank') }}</th>
                                <th scope="col" class="py-2 pr-3 font-extrabold">{{ __('Participant') }}</th>
                                <th scope="col" class="py-2 pr-3 font-extrabold">{{ __('Entry') }}</th>
                                <th scope="col" class="py-2 pr-3 text-right font-extrabold">{{ __('Score') }}</th>
                                @if ($can['judge'] && $competition->type === 'submission' && $resultsOpen)<th scope="col" class="py-2 font-extrabold">{{ __('Your score') }}</th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($entries as $entry)
                                <tr wire:key="e-{{ $entry->id }}" @class(['opacity-50' => in_array($entry->status, ['withdrawn', 'disqualified'], true)])>
                                    <td class="py-2 pr-3 text-lg font-black">{{ $entry->rank ?? '—' }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="font-bold">{{ $entry->user?->full_name }}</span>
                                        <span class="block text-sm text-slate">{{ $entry->user?->institution?->name }} · {{ $entryStatuses[$entry->status] ?? $entry->status }}</span>
                                        @if ($entry->status_reason)<span class="block text-sm text-danger">{{ $entry->status_reason }}</span>@endif
                                    </td>
                                    <td class="max-w-xs py-2 pr-3 text-sm">
                                        @if ($entry->submission_text)<details><summary class="cursor-pointer font-bold">{{ __('Read essay') }}</summary><p class="mt-1 whitespace-pre-line">{{ $entry->submission_text }}</p></details>@endif
                                        @if ($entry->submission_url)<a href="{{ $entry->submission_url }}" target="_blank" rel="noopener" class="link break-all">{{ __('Open link') }}</a>@endif
                                        @if ($entry->submission_file)<a href="{{ route('competitions.entry-file', $entry) }}" class="link">{{ __('Download file') }}</a>@endif
                                        @if ($entry->submitted_at)<span class="block text-slate">{{ $entry->submitted_at->translatedFormat('j M H:i') }}</span>@endif
                                    </td>
                                    <td class="py-2 pr-3 text-right tabular-nums">{{ $entry->score !== null ? number_format($entry->score, 1) : '—' }}@if ($competition->type === 'submission')<span class="block text-xs text-slate">{{ trans_choice(':count judge|:count judges', $entry->scores_count, ['count' => $entry->scores_count]) }}</span>@endif</td>
                                    @if ($can['judge'] && $competition->type === 'submission' && $resultsOpen)
                                        <td class="py-2">
                                            @if ($entry->status === 'submitted')
                                                <form wire:submit="saveScore({{ $entry->id }})" class="flex flex-wrap items-center gap-2">
                                                    <input wire:model="scores.{{ $entry->id }}" type="number" min="0" max="100" step="0.5" class="field mt-0 w-20" aria-label="{{ __('Score out of 100') }}">
                                                    <input wire:model="comments.{{ $entry->id }}" type="text" class="field mt-0 w-36" placeholder="{{ __('Comment') }}" aria-label="{{ __('Comment') }}">
                                                    <button type="submit" class="link">{{ __('Save') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                                @if ($can['approveResults'] && $resultsOpen && ! in_array($entry->status, ['withdrawn', 'disqualified'], true))
                                    <tr wire:key="dq-{{ $entry->id }}"><td></td><td colspan="4" class="pb-2">
                                        @if ($disqualifying === $entry->id)
                                            <form wire:submit="disqualify" class="flex flex-wrap gap-2"><input wire:model="reason" type="text" class="field mt-0 w-72" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}"><button type="submit" class="btn-ghost">{{ __('Disqualify') }}</button></form>
                                        @else
                                            <button type="button" wire:click="$set('disqualifying', {{ $entry->id }})" class="text-sm font-bold text-slate hover:text-danger">{{ __('Disqualify') }}</button>
                                        @endif
                                    </td></tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="py-8 text-center font-bold text-slate">{{ __('Nobody has registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($can['prizes'] && $resultsOpen)
                <form wire:submit="savePrizes" class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Prizes') }}</h2>
                    <p class="text-slate">{{ __('Prizes are awarded automatically when the results are published. Everyone with a score gets a certificate.') }}</p>
                    @foreach ($prizes as $index => $prize)
                        <div class="flex flex-wrap items-center gap-2" wire:key="prize-{{ $index }}">
                            <span class="font-bold">{{ __('Places') }}</span>
                            <input wire:model="prizes.{{ $index }}.rank_from" type="number" min="1" class="field mt-0 w-20" aria-label="{{ __('From') }}">
                            <span>–</span>
                            <input wire:model="prizes.{{ $index }}.rank_to" type="number" min="1" class="field mt-0 w-20" aria-label="{{ __('To') }}">
                            <select wire:model="prizes.{{ $index }}.reward_id" class="field mt-0 w-64" aria-label="{{ __('Reward') }}">
                                <option value="">{{ __('Choose a reward') }}</option>
                                @foreach ($rewards as $reward)<option value="{{ $reward->id }}">{{ $reward->name() }}</option>@endforeach
                            </select>
                            <button type="button" wire:click="removePrize({{ $index }})" class="link">{{ __('Remove') }}</button>
                        </div>
                    @endforeach
                    @error('prizes.*')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="addPrize" class="btn-ghost">{{ __('Add a prize') }}</button>
                        <button type="submit" class="btn-primary">{{ __('Save prizes') }}</button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="space-y-6">
            @if (in_array($competition->editorial_status, Review::EDITABLE, true) && $can['edit'] && $competition->publication_status === 'not_published')
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for review?') }}</h2>
                    @if ($competition->review_note)<p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $competition->review_note }}</p>@endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full" @disabled($problems)>{{ __('Send for review') }}</button>
                </section>
            @endif

            @if ($competition->editorial_status === Review::IN_REVIEW && $can['review'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Review') }}</h2>
                    @if ($ownSubmission && \App\Support\ContentWorkflow::fourEyes())
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the author') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($competition->editorial_status === Review::APPROVED && $can['publish'] && in_array($competition->publication_status, ['not_published', 'scheduled'], true))
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Publish to members') }}</h2>
                    <form wire:submit="publish" class="mt-4 space-y-3">
                        <label for="publishAt" class="block font-bold">{{ __('Goes live') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                        <input wire:model="publishAt" id="publishAt" type="datetime-local" class="field">
                        <button type="submit" class="btn-brand w-full">{{ __('Publish') }}</button>
                    </form>
                </section>
            @endif

            @if ($competition->publication_status === 'published' && $resultsOpen)
                <section class="space-y-3 rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Results') }}</h2>
                    @if ($competition->results_status === Competition::RESULTS_IN_REVIEW)
                        @if ($can['approveResults'])
                            <p class="text-slate">{{ __('Check the ranking. Publishing tells every participant, issues certificates and awards the prizes.') }}</p>
                            <label for="resultsNote" class="block font-bold">{{ __('Notes (if returning)') }}</label>
                            <textarea wire:model="reviewNote" id="resultsNote" rows="2" class="field mt-0"></textarea>
                            <button type="button" wire:click="approveResults" wire:confirm="{{ __('Publish these results?') }}" class="btn-brand w-full">{{ __('Approve and publish results') }}</button>
                            <button type="button" wire:click="returnResults" class="btn-ghost w-full">{{ __('Return for changes') }}</button>
                        @else
                            <p class="font-bold">{{ __('Waiting for approval.') }}</p>
                        @endif
                    @elseif ($competition->hasEnded())
                        <p class="text-slate">{{ $competition->type === 'quiz' ? __('Scores come from each participant\'s best quiz attempt during the competition.') : __('Make sure every submission is scored by the judges.') }}</p>
                        <button type="button" wire:click="submitResults" class="btn-primary w-full">{{ __('Rank and send for approval') }}</button>
                    @else
                        <p class="text-slate">{{ __('Results can be prepared after :date.', ['date' => $competition->ends_at?->translatedFormat('j M Y H:i')]) }}</p>
                    @endif
                </section>
            @endif

            @if ($can['publish'] && in_array($competition->publication_status, ['published', 'scheduled'], true) && $resultsOpen)
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Cancel') }}</h2>
                    <textarea wire:model="reason" rows="2" class="field mt-0" aria-label="{{ __('Reason') }}" placeholder="{{ __('Reason') }}"></textarea>
                    <button type="button" wire:click="cancel" wire:confirm="{{ __('Cancel this competition and tell participants?') }}" class="btn w-full border-2 border-line hover:border-danger hover:text-danger">{{ __('Cancel competition') }}</button>
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
