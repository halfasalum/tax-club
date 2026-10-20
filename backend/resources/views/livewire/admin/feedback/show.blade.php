@php
    use App\Support\FeedbackDesk;

    $categories = FeedbackDesk::categoryLabels();
    $priorities = FeedbackDesk::priorityLabels();
    $areas = FeedbackDesk::areaLabels();
    $statuses = FeedbackDesk::statusLabels();
    $actions = [
        'submitted' => __('Received'), 'classified' => __('Classified'), 'assigned' => __('Assigned'), 'unassigned' => __('Unassigned'),
        'note' => __('Internal note'), 'responded' => __('Response sent'), 'closed' => __('Closed'), 'reopened' => __('Reopened'), 'member_message' => __('Member added a message'),
    ];
@endphp
<div>
    <a wire:navigate href="{{ route('admin.feedback.index') }}" class="link">← {{ __('Feedback') }}</a>
    <div class="mt-4">
        <p class="font-receipt text-slate">{{ $feedback->reference }}</p>
        <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $feedback->subject }}</h1>
        <p class="mt-2 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-ink px-2.5 py-0.5 text-xs font-extrabold text-brand">{{ $statuses[$feedback->status] ?? $feedback->status }}</span>
            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-extrabold', 'bg-danger text-white' => in_array($feedback->priority, ['urgent', 'high'], true), 'bg-mist' => ! in_array($feedback->priority, ['urgent', 'high'], true)])>{{ $priorities[$feedback->priority] ?? '' }}</span>
            @if ($feedback->isSafeguarding())<span class="rounded-full bg-[#fef3f2] px-2.5 py-0.5 text-xs font-extrabold text-danger">{{ __('Safeguarding · confidential') }}</span>@endif
        </p>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <p class="text-sm font-extrabold text-slate">{{ $categories[$feedback->category] ?? '' }}@if ($relatedLabel) · {{ $relatedLabel }}@endif · {{ $feedback->created_at->translatedFormat('j M Y, H:i') }} · {{ strtoupper($feedback->channel) }}</p>
                <p class="mt-3 whitespace-pre-line text-lg">{{ $feedback->message }}</p>
                <p class="mt-4 rounded-xl bg-mist p-3 text-sm">
                    <span class="font-extrabold">{{ $member?->full_name }}</span>
                    · <span class="font-receipt">{{ $member?->membership_id }}</span>
                    · {{ $member?->institution?->name }}@if ($member?->isMinor()) · <span class="font-extrabold">{{ __('Under 18') }}</span>@endif
                </p>
            </section>

            @if ($can['respond'] && $feedback->isOpen())
                <form wire:submit="respond" class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Respond to the member') }}</h2>
                    <p class="mt-1 text-sm text-slate">{{ __('The member sees this response. Keep it clear and kind.') }}</p>
                    <textarea wire:model="response" rows="5" class="field mt-3" aria-label="{{ __('Response') }}">{{ $feedback->response }}</textarea>
                    <label class="mt-3 flex items-center gap-2 font-bold"><input type="checkbox" wire:model="closeAfter" class="size-4 accent-ink"> {{ __('Resolved: close after sending') }}</label>
                    <button type="submit" class="btn-primary mt-3">{{ __('Send response') }}</button>
                </form>
            @elseif ($feedback->response)
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="font-black">{{ __('Response sent') }}</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $feedback->response }}</p>
                </section>
            @endif

            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('History') }}</h2>
                <ol class="mt-3 space-y-3 border-l-4 border-brand pl-4">
                    @foreach ($history as $step)
                        <li>
                            <p class="font-extrabold">{{ $actions[$step->action] ?? $step->action }}@if ($step->internal) <span class="text-xs font-bold text-slate">({{ __('not shown to the member') }})</span>@endif</p>
                            <p class="text-sm text-slate">{{ $step->user?->full_name ?? __('System') }} · {{ $step->created_at->translatedFormat('j M Y H:i') }}</p>
                            @if ($step->note)<p class="whitespace-pre-line text-sm">{{ $step->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                @if ($feedback->isOpen())
                    <form wire:submit="addNote" class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="min-w-0 flex-1">
                            <label for="note" class="block font-bold">{{ __('Internal note') }}</label>
                            <input wire:model="note" id="note" type="text" class="field">
                        </div>
                        <button type="submit" class="btn-ghost">{{ __('Add note') }}</button>
                    </form>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @if ($can['manage'])
                <form wire:submit="classify" class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Classify') }}</h2>
                    <div>
                        <label for="category" class="block font-bold">{{ __('Category') }}</label>
                        <select wire:model="category" id="category" class="field">@foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="priority" class="block font-bold">{{ __('Priority') }}</label>
                        <select wire:model="priority" id="priority" class="field">@foreach ($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="area" class="block font-bold">{{ __('Area') }}</label>
                        <select wire:model="area" id="area" class="field">@foreach ($areas as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <button type="submit" class="btn-ghost w-full">{{ __('Save') }}</button>
                </form>
            @endif

            <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Assigned to') }}</h2>
                <p class="font-bold">{{ $feedback->assignee?->full_name ?? __('Nobody yet') }}</p>
                @if ($can['manage'])
                    <form wire:submit="assign" class="space-y-2">
                        <label for="assignee" class="sr-only">{{ __('Officer') }}</label>
                        <select wire:model="assignee" id="assignee" class="field">
                            <option value="">{{ __('Nobody') }}</option>
                            @foreach ($officers as $officer)<option value="{{ $officer->user_id }}">{{ $officer->full_name }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn-ghost w-full">{{ __('Assign') }}</button>
                    </form>
                @endif
                @if ($feedback->assigned_to !== auth()->id() && $feedback->isOpen() && ($can['respond'] || $can['manage']))
                    <button type="button" wire:click="takeIt" class="link">{{ __('Assign to me') }}</button>
                @endif
            </section>

            @if ($can['respond'])
                <section class="space-y-3 rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ $feedback->isOpen() ? __('Close') : __('Reopen') }}</h2>
                    <label for="reason" class="block font-bold">{{ $feedback->isOpen() ? __('Note (needed if you did not respond)') : __('Reason') }}</label>
                    <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                    @if ($feedback->isOpen())
                        <button type="button" wire:click="close" class="btn-ghost w-full">{{ __('Close') }}</button>
                    @else
                        <button type="button" wire:click="reopen" class="btn-ghost w-full">{{ __('Reopen') }}</button>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
