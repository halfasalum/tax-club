@php
    use App\Models\Broadcast;
    use App\Support\Review;

    $actions = [
        'created' => __('Created'), 'submitted' => __('Sent for approval'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'),
        'rejected' => __('Rejected'), 'back_to_draft' => __('Back to draft'), 'scheduled' => __('Scheduled'), 'sent' => __('Sent'), 'cancelled' => __('Cancelled'),
    ];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.broadcasts.index') }}" class="link">← {{ __('Broadcasts') }}</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $broadcast->code }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $broadcast->title }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2">
                <x-editorial-status :status="$broadcast->status" />
                @if ($broadcast->status === 'scheduled')<span class="text-slate">{{ $broadcast->send_at?->translatedFormat('j M Y H:i') }}</span>@endif
            </p>
        </div>
        @if ($can['create'] && in_array($broadcast->status, [...Review::EDITABLE, Review::IN_REVIEW, Review::APPROVED], true))
            <a wire:navigate href="{{ route('admin.broadcasts.edit', $broadcast) }}" class="btn-ghost">{{ __('Edit') }}</a>
        @endif
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Message') }}</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-mist p-4">
                        <p class="text-sm font-extrabold text-slate">Kiswahili</p>
                        <p class="mt-1 whitespace-pre-line">TRA Tax Club: {{ $broadcast->message_sw }}</p>
                    </div>
                    <div class="rounded-2xl bg-mist p-4">
                        <p class="text-sm font-extrabold text-slate">English</p>
                        <p class="mt-1 whitespace-pre-line">{{ $broadcast->message_en ? 'TRA Tax Club: '.$broadcast->message_en : __('Not written. English members get the Kiswahili message.') }}</p>
                    </div>
                </div>
                @if ($broadcast->link_url)<p class="mt-3 break-all"><span class="font-extrabold">{{ __('Link') }}:</span> {{ $broadcast->link_url }}</p>@endif
                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-ink p-4 text-white"><dt class="text-sm font-extrabold text-white/70">{{ $broadcast->status === Broadcast::SENT ? __('Sent to') : __('Will reach') }}</dt><dd class="text-2xl font-black text-brand tabular-nums">{{ number_format($recipients) }}</dd></div>
                    <div class="rounded-2xl bg-mist p-4"><dt class="text-sm font-extrabold text-slate">{{ __('Channels') }}</dt><dd class="text-lg font-black">{{ collect([$broadcast->send_sms ? 'SMS' : null, $broadcast->send_inbox ? __('Inbox') : null])->filter()->implode(' + ') }}</dd></div>
                    <div class="rounded-2xl bg-mist p-4"><dt class="text-sm font-extrabold text-slate">{{ __('SMS parts per member') }}</dt><dd class="text-lg font-black">{{ $broadcast->send_sms ? $broadcast->smsParts() : '—' }}</dd></div>
                </dl>
                <p class="mt-3"><span class="font-extrabold">{{ __('Who receives it') }}:</span> {{ \App\Support\Audience::describe($broadcast->audience) }}</p>
                @if ($broadcast->status === Broadcast::SENT)<p class="mt-3 text-slate">{{ __('Delivery of each SMS is shown in the SMS log.') }}</p>@endif
            </section>

            @if ($problems)
                <ul class="list-disc space-y-1 rounded-2xl bg-[#fef3f2] p-5 pl-10 font-semibold text-danger">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            @endif
        </div>

        <aside class="space-y-6">
            @if (in_array($broadcast->status, Review::EDITABLE, true) && $can['create'])
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for approval?') }}</h2>
                    @if ($broadcast->review_note)<p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $broadcast->review_note }}</p>@endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full" @disabled($problems)>{{ __('Send for approval') }}</button>
                </section>
            @endif

            @if ($broadcast->status === Review::IN_REVIEW && $can['approve'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Approval') }}</h2>
                    @if ($ownSubmission && \App\Support\ContentWorkflow::fourEyes())
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate">{{ __('Check the wording, both languages, the audience and the SMS cost.') }}</p>
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the author') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" wire:confirm="{{ __('Reject this broadcast?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if (in_array($broadcast->status, [Review::APPROVED, Broadcast::SCHEDULED], true) && $can['approve'])
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Send') }}</h2>
                    <form wire:submit="send" class="mt-4 space-y-3">
                        <div>
                            <label for="sendAt" class="block font-bold">{{ __('Send at') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                            <input wire:model="sendAt" id="sendAt" type="datetime-local" class="field">
                        </div>
                        <button type="submit" class="btn-brand w-full" wire:confirm="{{ __('Send this to :count members?', ['count' => number_format($recipients)]) }}">{{ __('Send or schedule') }}</button>
                    </form>
                </section>
            @endif

            @if (! in_array($broadcast->status, [Broadcast::SENDING, Broadcast::SENT, Broadcast::CANCELLED], true) && ($can['approve'] || $can['create']))
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Cancel') }}</h2>
                    <label for="reason" class="mt-3 block font-bold">{{ __('Reason') }}</label>
                    <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                    <button type="button" wire:click="cancel" class="btn border-2 border-line mt-3 w-full hover:border-danger hover:text-danger">{{ __('Cancel broadcast') }}</button>
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
