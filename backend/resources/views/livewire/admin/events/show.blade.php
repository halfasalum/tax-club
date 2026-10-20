@php
    use App\Models\Event;
    use App\Models\EventRegistration;
    use App\Support\Audience;
    use App\Support\EventLabels;
    use App\Support\Review;

    $actions = [
        'created' => __('Created'), 'submitted' => __('Sent for review'), 'approved' => __('Approved'), 'returned' => __('Returned for changes'),
        'rejected' => __('Rejected'), 'published' => __('Published'), 'scheduled' => __('Scheduled'), 'went_live' => __('Went live'),
        'edited' => __('Details changed'), 'back_to_draft' => __('Back to draft'), 'postponed' => __('Postponed'), 'cancelled' => __('Cancelled'),
        'closed' => __('Closed'), 'archived' => __('Archived'), 'update_posted' => __('Update posted'), 'update_removed' => __('Update removed'),
    ];
    $lists = ['registered' => __('Registered'), 'waitlisted' => __('Waitlist'), 'attended' => __('Attended'), 'cancelled' => __('Cancelled'), 'all' => __('All')];
    $statusLabels = EventLabels::registrationStatuses();
    $methods = EventLabels::checkInMethods();
    $categories = Audience::categoryLabels();
    $kinds = ['update' => __('Update'), 'instructions' => __('Instructions'), 'material' => __('Material')];
    $live = in_array($event->publication_status, [Event::PUBLISHED, Event::POSTPONED], true);
@endphp
<div>
    <a wire:navigate href="{{ route('admin.events.index') }}" class="link">← {{ __('Events') }}</a>
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-receipt text-slate">{{ $event->code }} · {{ EventLabels::types()[$event->type] ?? $event->type }}</p>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ $event->title }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2">
                <x-editorial-status :status="$event->editorial_status" />
                <x-editorial-status :status="$event->displayStatus()" />
                @if ($event->publish_at && $event->publication_status === Event::SCHEDULED)<span class="text-slate">{{ __('goes live :date', ['date' => $event->publish_at->translatedFormat('j M Y H:i')]) }}</span>@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($can['checkIn'] && $checkInOpen)
                <a wire:navigate href="{{ route('admin.events.check-in', $event) }}" class="btn-brand">{{ __('Check members in') }}</a>
            @endif
            @if ($can['edit'] && ! in_array($event->publication_status, [Event::CLOSED, Event::ARCHIVED], true))
                <a wire:navigate href="{{ route('admin.events.edit', $event) }}" class="btn-ghost">{{ __('Edit') }}</a>
            @endif
            @if ($can['attendees'] && $summary['registered'] + $summary['waitlisted'] > 0)
                <a href="{{ route('admin.events.export', $event) }}" class="btn-ghost">{{ __('Download list (CSV)') }}</a>
            @endif
        </div>
    </div>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif
    @error('action')<p class="mt-6 rounded-2xl bg-[#fef3f2] px-5 py-4 font-bold text-danger" role="alert">{{ $message }}</p>@enderror
    @if ($event->status_reason && in_array($event->publication_status, [Event::POSTPONED, Event::CANCELLED], true))
        <p class="mt-6 rounded-2xl bg-[#fff7ed] px-5 py-4 font-bold text-[#9a3412]">{{ $event->publication_status === Event::CANCELLED ? __('Cancelled') : __('Postponed') }}: {{ $event->status_reason }}</p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-3xl border-2 border-line bg-white p-6">
                <h2 class="text-xl font-black">{{ __('Details') }}</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    @foreach ([
                        [__('Starts'), $event->starts_at?->translatedFormat('l j M Y, H:i') ?? '—'],
                        [__('Ends'), $event->ends_at?->translatedFormat('l j M Y, H:i') ?? '—'],
                        [__('Format'), EventLabels::modes()[$event->mode] ?? $event->mode],
                        [__('Venue'), trim($event->location.($event->region ? ', '.$event->region : '')) ?: '—'],
                        [__('Organizer'), $event->organizer ?: '—'],
                        [__('Who it is for'), Audience::describe($event->audience)],
                        [__('Registration'), $event->registration_required ? __('Required, closes :date', ['date' => $event->registrationDeadline()?->translatedFormat('j M Y H:i') ?? '—']) : __('Not needed')],
                        [__('Places'), $event->capacity ? number_format($event->capacity).($event->waitlist_enabled ? ' · '.__('waitlist on') : '') : __('No limit')],
                        [__('Public website'), $event->is_public ? __('Shown') : __('Members only')],
                    ] as [$label, $value])
                        <div><dt class="inline font-extrabold">{{ $label }}:</dt> <dd class="inline">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($event->online_url)<p class="mt-3 break-all"><span class="font-extrabold">{{ __('Online meeting link') }}:</span> <a href="{{ $event->online_url }}" class="link" target="_blank" rel="noopener">{{ $event->online_url }}</a></p>@endif
                @if ($event->objective)<p class="mt-4 rounded-xl bg-brand-wash p-3"><span class="font-extrabold">{{ __('Objective') }}:</span> {{ $event->objective }}</p>@endif
                <div class="mt-4 max-w-[65ch] whitespace-pre-line leading-relaxed text-ink-soft">{{ $event->description }}</div>
                @if ($event->registration_question)<p class="mt-4 text-slate"><span class="font-extrabold">{{ __('Question for registrants') }}:</span> {{ $event->registration_question }}</p>@endif
            </section>

            @if ($problems && $event->publication_status === Event::NOT_PUBLISHED)
                <ul class="list-disc space-y-1 rounded-2xl bg-[#fef3f2] p-5 pl-10 font-semibold text-danger">
                    @foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach
                </ul>
            @endif

            @if ($event->publication_status !== Event::NOT_PUBLISHED)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-xl font-black">{{ __('Participation') }}</h2>
                        @if ($event->closed_at)<p class="text-sm text-slate">{{ __('Closed :date', ['date' => $event->closed_at->translatedFormat('j M Y')]) }}</p>@endif
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ([
                            [__('Registered'), number_format($summary['registered']).($summary['capacity'] ? ' / '.number_format($summary['capacity']) : '')],
                            [__('Attended'), number_format($summary['attended'])],
                            [__('Attendance rate'), $summary['rate'] !== null ? $summary['rate'].'%' : '—'],
                            [__('On the waitlist'), number_format($summary['waitlisted'])],
                            [__('Absent'), number_format($summary['absent'])],
                            [__('Walk-ins'), number_format($summary['walk_ins'])],
                            [__('Cancelled'), number_format($summary['cancelled'])],
                            [__('Updates posted'), number_format($summary['updates'])],
                        ] as [$label, $value])
                            <div class="rounded-2xl bg-mist p-4"><dt class="text-sm font-extrabold text-slate">{{ $label }}</dt><dd class="text-2xl font-black tabular-nums">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($summary['registered'] > 0)
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <h3 class="font-black">{{ __('By membership category') }}</h3>
                                <table class="mt-2 w-full text-left">
                                    <thead class="text-sm text-slate"><tr><th scope="col" class="py-1 font-extrabold">{{ __('Category') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Registered') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Attended') }}</th></tr></thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($summary['by_category'] as $category => $row)
                                            <tr><td class="py-1">{{ $categories[$category] ?? ($category === 'staff' ? __('Staff') : $category) }}</td><td class="py-1 text-right tabular-nums">{{ $row['registered'] }}</td><td class="py-1 text-right tabular-nums">{{ $row['attended'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h3 class="font-black">{{ __('By region') }}</h3>
                                <table class="mt-2 w-full text-left">
                                    <thead class="text-sm text-slate"><tr><th scope="col" class="py-1 font-extrabold">{{ __('Region') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Registered') }}</th><th scope="col" class="py-1 text-right font-extrabold">{{ __('Attended') }}</th></tr></thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach (array_slice($summary['by_region'], 0, 8, true) as $region => $row)
                                            <tr><td class="py-1">{{ $region }}</td><td class="py-1 text-right tabular-nums">{{ $row['registered'] }}</td><td class="py-1 text-right tabular-nums">{{ $row['attended'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($summary['by_method'])
                            <p class="mt-4 text-sm text-slate">{{ __('Check-in by') }}: {{ collect($summary['by_method'])->map(fn ($count, $method) => ($methods[$method] ?? $method).' '.$count)->implode(' · ') }}</p>
                        @endif
                    @endif
                    @if ($event->closing_notes)<p class="mt-4 rounded-xl bg-mist p-3"><span class="font-extrabold">{{ __('Closing notes') }}:</span> {{ $event->closing_notes }}</p>@endif
                </section>

                @if ($can['attendees'])
                    <section class="rounded-3xl border-2 border-line bg-white p-6">
                        <h2 class="text-xl font-black">{{ __('Registrations') }}</h2>
                        <div class="mt-4 flex flex-wrap gap-1 rounded-2xl bg-mist p-1.5" role="tablist" aria-label="{{ __('Registrations') }}">
                            @foreach ($lists as $value => $label)
                                <button type="button" role="tab" wire:click="$set('listView', '{{ $value }}')" aria-selected="{{ $listView === $value ? 'true' : 'false' }}"
                                        @class(['rounded-xl px-3 py-1.5 font-extrabold', 'bg-ink text-brand' => $listView === $value, 'text-slate hover:bg-white' => $listView !== $value])>{{ $label }}</button>
                            @endforeach
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search by name or membership number') }}" class="field mt-4" aria-label="{{ __('Search by name or membership number') }}">

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full min-w-[40rem] text-left">
                                <thead class="border-b-2 border-line text-sm">
                                    <tr>
                                        <th scope="col" class="py-2 pr-3 font-extrabold">{{ __('Member') }}</th>
                                        <th scope="col" class="py-2 pr-3 font-extrabold">{{ __('Status') }}</th>
                                        <th scope="col" class="py-2 pr-3 font-extrabold">{{ $listView === 'waitlisted' ? __('Position') : __('Registered') }}</th>
                                        <th scope="col" class="py-2 font-extrabold"><span class="sr-only">{{ __('Actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @forelse ($this->registrations as $index => $registration)
                                        <tr wire:key="reg-{{ $registration->id }}">
                                            <td class="py-2 pr-3">
                                                <span class="font-bold">{{ $registration->user?->full_name }}</span>
                                                <span class="block text-sm text-slate"><span class="font-receipt">{{ $registration->user?->membership_id }}</span> · {{ $registration->user?->institution?->name }}</span>
                                                @if ($registration->answer)<span class="mt-1 block text-sm italic">“{{ $registration->answer }}”</span>@endif
                                            </td>
                                            <td class="py-2 pr-3">
                                                <span class="font-bold">{{ $statusLabels[$registration->status] ?? $registration->status }}</span>
                                                @if ($registration->checked_in_at)<span class="block text-sm text-slate">{{ $registration->checked_in_at->format('H:i') }} · {{ $methods[$registration->check_in_method] ?? '' }}@if ($registration->walk_in) · {{ __('walk-in') }}@endif</span>@endif
                                                @if ($registration->correction_reason)<span class="block text-sm text-slate">{{ __('Corrected') }}: {{ $registration->correction_reason }}</span>@endif
                                            </td>
                                            <td class="whitespace-nowrap py-2 pr-3 text-sm">
                                                {{ $listView === 'waitlisted' ? '#'.($this->registrations->firstItem() + $index) : $registration->registered_at?->translatedFormat('j M, H:i') }}
                                            </td>
                                            <td class="py-2 text-right">
                                                @if ($can['checkIn'])
                                                    @if ($checkInOpen && in_array($registration->status, [EventRegistration::REGISTERED, EventRegistration::WAITLISTED], true))
                                                        <button type="button" wire:click="checkIn({{ $registration->id }})" class="link">{{ __('Check in') }}</button>
                                                    @elseif (in_array($registration->status, [EventRegistration::REGISTERED, EventRegistration::ATTENDED], true) && $event->starts_at?->isPast())
                                                        <button type="button" wire:click="startCorrection({{ $registration->id }})" class="link">{{ __('Correct') }}</button>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($correcting === $registration->id)
                                            <tr wire:key="fix-{{ $registration->id }}">
                                                <td colspan="4" class="bg-brand-wash p-3">
                                                    <form wire:submit="saveCorrection" class="flex flex-wrap items-end gap-3">
                                                        <div class="min-w-0 flex-1">
                                                            <label for="correctionReason" class="block font-bold">{{ $registration->status === EventRegistration::ATTENDED ? __('Why remove this attendance?') : __('Why mark as attended?') }}</label>
                                                            <input wire:model="correctionReason" id="correctionReason" type="text" class="field">
                                                        </div>
                                                        <button type="submit" class="btn-primary">{{ $registration->status === EventRegistration::ATTENDED ? __('Remove attendance') : __('Mark attended') }}</button>
                                                        <button type="button" wire:click="$set('correcting', null)" class="btn-ghost">{{ __('Cancel') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr><td colspan="4" class="py-8 text-center font-bold text-slate">{{ __('Nobody here yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $this->registrations->links() }}</div>
                    </section>
                @endif

                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Updates and materials') }}</h2>
                    <ul class="mt-3 divide-y divide-line">
                        @forelse ($updates as $update)
                            <li class="py-3" wire:key="update-{{ $update->id }}">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-mist px-2 py-0.5 text-xs font-extrabold">{{ $kinds[$update->kind] ?? $update->kind }}</span>
                                    <span class="font-black">{{ $update->title }}</span>
                                    @if ($update->registered_only)<span class="text-sm text-slate">· {{ __('registered members only') }}</span>@endif
                                </p>
                                @if ($update->body)<p class="mt-1 whitespace-pre-line">{{ $update->body }}</p>@endif
                                <p class="mt-1 flex flex-wrap gap-3 text-sm">
                                    @if ($update->link_url)<a href="{{ $update->link_url }}" class="link" target="_blank" rel="noopener">{{ __('Open link') }}</a>@endif
                                    @if ($update->file_path)<a href="{{ route('events.material', [$event, $update]) }}" class="link">{{ $update->file_name }}</a>@endif
                                    <span class="text-slate">{{ $update->author?->full_name }} · {{ $update->created_at->translatedFormat('j M Y H:i') }}</span>
                                    @if ($can['edit'])<button type="button" wire:click="removeUpdate({{ $update->id }})" wire:confirm="{{ __('Remove this update?') }}" class="link text-danger">{{ __('Remove') }}</button>@endif
                                </p>
                            </li>
                        @empty
                            <li class="py-3 text-slate">{{ __('Nothing posted yet.') }}</li>
                        @endforelse
                    </ul>

                    @if ($can['edit'] && $live)
                        <form wire:submit="postUpdate" class="mt-4 space-y-3 rounded-2xl bg-mist p-4">
                            <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                                <div>
                                    <label for="updateKind" class="block font-bold">{{ __('Kind') }}</label>
                                    <select wire:model="updateKind" id="updateKind" class="field">
                                        @foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="updateTitle" class="block font-bold">{{ __('Title') }}</label>
                                    <input wire:model="updateTitle" id="updateTitle" type="text" @class(['field', 'field-error' => $errors->has('updateTitle')])>
                                    @error('updateTitle')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div>
                                <label for="updateBody" class="block font-bold">{{ __('Message') }}</label>
                                <textarea wire:model="updateBody" id="updateBody" rows="3" class="field"></textarea>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="updateLink" class="block font-bold">{{ __('Link') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                                    <input wire:model="updateLink" id="updateLink" type="url" class="field">
                                    @error('updateLink')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="updateFile" class="block font-bold">{{ __('File') }} <span class="font-semibold text-slate">({{ __('PDF, image or slides, up to 10 MB') }})</span></label>
                                    <input wire:model="updateFile" id="updateFile" type="file" class="mt-1 block w-full text-sm">
                                    @error('updateFile')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <label class="flex items-center gap-2 font-bold"><input type="checkbox" wire:model="updateRegisteredOnly" class="size-4 accent-ink"> {{ __('Only for registered members') }}</label>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ __('Post and tell registered members') }}</button>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if (in_array($event->editorial_status, Review::EDITABLE, true) && $can['edit'] && $event->publication_status === Event::NOT_PUBLISHED)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Ready for approval?') }}</h2>
                    @if ($event->review_note)<p class="mt-2 rounded-xl bg-[#fef3f2] p-3"><span class="font-extrabold">{{ __('Reviewer notes') }}:</span> {{ $event->review_note }}</p>@endif
                    <button type="button" wire:click="submit" class="btn-primary mt-4 w-full" @disabled($problems)>{{ __('Send for approval') }}</button>
                </section>
            @endif

            @if ($event->editorial_status === Review::IN_REVIEW && $can['review'])
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Approval') }}</h2>
                    @if ($ownSubmission && \App\Support\ContentWorkflow::fourEyes())
                        <p class="mt-3 rounded-xl bg-brand-wash p-3 font-bold">{{ __('You sent this for review, so another reviewer must decide.') }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate">{{ __('Check the date, venue, audience and safety arrangements for young members.') }}</p>
                        <label for="reviewNote" class="mt-4 block font-bold">{{ __('Notes for the organizer') }}</label>
                        <textarea wire:model="reviewNote" id="reviewNote" rows="3" class="field"></textarea>
                        <div class="mt-3 grid gap-2">
                            <button type="button" wire:click="approve" class="btn-primary">{{ __('Approve') }}</button>
                            <button type="button" wire:click="returnForChanges" class="btn-ghost">{{ __('Return for changes') }}</button>
                            <button type="button" wire:click="reject" wire:confirm="{{ __('Reject this event?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Reject') }}</button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($event->editorial_status === Review::APPROVED && $can['publish'] && in_array($event->publication_status, [Event::NOT_PUBLISHED, Event::SCHEDULED], true))
                <section class="rounded-3xl border-4 border-savanna bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Publish to members') }}</h2>
                    <form wire:submit="publish" class="mt-4 space-y-3">
                        <div>
                            <label for="publishAt" class="block font-bold">{{ __('Goes live') }} <span class="font-semibold text-slate">({{ __('empty = now') }})</span></label>
                            <input wire:model="publishAt" id="publishAt" type="datetime-local" class="field">
                        </div>
                        <button type="submit" class="btn-brand w-full">{{ $event->publication_status === Event::SCHEDULED ? __('Change schedule') : __('Publish') }}</button>
                    </form>
                </section>
            @endif

            @if ($can['publish'] && $live && ! $event->hasEnded())
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Postpone or cancel') }}</h2>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label for="reason" class="block font-bold">{{ __('Reason (sent to registered members)') }}</label>
                            <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                        </div>
                        <div class="grid gap-2">
                            <label for="newStartsAt" class="block font-bold">{{ __('New start') }} <span class="font-semibold text-slate">({{ __('empty = to be announced') }})</span></label>
                            <input wire:model="newStartsAt" id="newStartsAt" type="datetime-local" class="field">
                            @error('newStartsAt')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                            <label for="newEndsAt" class="block font-bold">{{ __('New end') }}</label>
                            <input wire:model="newEndsAt" id="newEndsAt" type="datetime-local" class="field">
                            @error('newEndsAt')<p class="font-semibold text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-2">
                            <button type="button" wire:click="postpone" class="btn-ghost">{{ __('Postpone') }}</button>
                            <button type="button" wire:click="cancel" wire:confirm="{{ __('Cancel this event and tell everyone who registered?') }}" class="btn border-2 border-line hover:border-danger hover:text-danger">{{ __('Cancel event') }}</button>
                        </div>
                    </div>
                </section>
            @endif

            @if ($can['publish'] && (($live && $event->hasEnded()) || $event->publication_status === Event::CANCELLED))
                <section class="rounded-3xl border-4 border-ink bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Close the event') }}</h2>
                    <p class="mt-1 text-sm text-slate">{{ __('Closing makes the participation summary final. Members who did not check in count as absent.') }}</p>
                    <label for="closingNotes" class="mt-3 block font-bold">{{ __('Notes for the report') }} <span class="font-semibold text-slate">({{ __('optional') }})</span></label>
                    <textarea wire:model="closingNotes" id="closingNotes" rows="3" class="field"></textarea>
                    <button type="button" wire:click="close" class="btn-primary mt-3 w-full">{{ __('Close event') }}</button>
                </section>
            @endif

            @if ($can['publish'] && $event->publication_status === Event::CLOSED)
                <section class="rounded-3xl border-2 border-line bg-white p-6">
                    <h2 class="text-xl font-black">{{ __('Archive') }}</h2>
                    <label for="reason" class="mt-3 block font-bold">{{ __('Reason') }}</label>
                    <textarea wire:model="reason" id="reason" rows="2" class="field"></textarea>
                    <button type="button" wire:click="archive" class="btn-ghost mt-3 w-full">{{ __('Archive') }}</button>
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
