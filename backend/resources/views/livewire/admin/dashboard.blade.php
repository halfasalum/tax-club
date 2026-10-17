@php
    $statusLabels = [
        'pending_verification' => [__('Waiting'), 'bg-brand text-ink'],
        'active' => [__('Approved'), 'bg-savanna text-white'],
        'rejected' => [__('Rejected'), 'bg-danger text-white'],
        'suspended' => [__('Suspended'), 'bg-ink text-white'],
    ];
@endphp
<div>
    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Overview') }}</h1>

    @if ($pendingRegistrations > 0 && auth()->user()->hasControl('view_registrations'))
        <a href="{{ route('admin.registrations') }}" class="sun-hatch mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-4 border-ink px-6 py-5 shadow-[6px_6px_0_var(--color-ink)] transition hover:-translate-y-0.5">
            <span class="flex items-center gap-4">
                <span class="grid size-14 place-items-center rounded-full bg-ink text-2xl font-black text-brand">{{ $pendingRegistrations > 99 ? '99+' : $pendingRegistrations }}</span>
                <span class="text-xl font-black">{{ trans_choice(':count registration is waiting for confirmation|:count registrations are waiting for confirmation', $pendingRegistrations, ['count' => number_format($pendingRegistrations)]) }}</span>
            </span>
            <span class="btn-primary">{{ __('Review registrations') }}</span>
        </a>
    @endif

    <dl class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            [__('Active members'), $activeMembers],
            [__('Institutions'), $institutions],
            [__('Published content'), $publishedContent],
            [__('Upcoming events'), $upcomingEvents],
            [__('Open opportunities'), $openOpportunities],
            [__('Quiz attempts'), $quizAttempts],
        ] as [$label, $value])
            <div class="rounded-2xl border-2 border-line border-t-8 border-t-brand bg-white px-5 py-4">
                <dt class="font-extrabold text-slate">{{ $label }}</dt>
                <dd class="mt-1 text-3xl font-black tabular-nums">{{ number_format($value) }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="mt-12" aria-labelledby="recent-heading">
        <h2 id="recent-heading" class="text-2xl font-black">{{ __('Recent registrations') }}</h2>
        @if ($recentRegistrations->isEmpty())
            <p class="mt-4 rounded-2xl bg-white p-6 text-lg text-slate">{{ __('New registrations will appear here.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                <table class="w-full text-left">
                    <thead class="bg-ink text-white">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Name') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Institution') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Reference') }}</th>
                            <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($recentRegistrations as $registration)
                            @php([$statusLabel, $statusClasses] = $statusLabels[$registration->account_status] ?? [$registration->account_status, 'bg-mist'])
                            <tr class="hover:bg-brand-wash/60">
                                <td class="px-5 py-3.5 font-extrabold">{{ $registration->full_name }}</td>
                                <td class="px-5 py-3.5">{{ $registration->institution?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 font-receipt">{{ $registration->registration_reference }}</td>
                                <td class="px-5 py-3.5"><span class="rounded-full px-3 py-1 text-sm font-extrabold {{ $statusClasses }}">{{ $statusLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
