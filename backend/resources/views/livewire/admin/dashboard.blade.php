@php
    $statusLabels = [
        'pending_verification' => __('Waiting'),
        'active' => __('Approved'),
        'rejected' => __('Rejected'),
        'suspended' => __('Suspended'),
    ];
@endphp
<div>
    <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Overview') }}</h1>

    @if ($pendingRegistrations > 0 && auth()->user()->hasControl('view_registrations'))
        <a href="{{ route('admin.registrations') }}" class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-flag px-6 py-5 text-ink hover:bg-[#ffc81f]">
            <span class="text-lg font-bold">{{ trans_choice(':count registration is waiting for confirmation|:count registrations are waiting for confirmation', $pendingRegistrations, ['count' => number_format($pendingRegistrations)]) }}</span>
            <span class="font-semibold underline underline-offset-4">{{ __('Review registrations') }}</span>
        </a>
    @endif

    <dl class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-line bg-line md:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            [__('Active members'), $activeMembers],
            [__('Institutions'), $institutions],
            [__('Published content'), $publishedContent],
            [__('Upcoming events'), $upcomingEvents],
            [__('Open opportunities'), $openOpportunities],
            [__('Quiz attempts'), $quizAttempts],
        ] as [$label, $value])
            <div class="bg-white px-5 py-4">
                <dt class="text-sm text-slate">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-extrabold tabular-nums">{{ number_format($value) }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="mt-12" aria-labelledby="recent-heading">
        <h2 id="recent-heading" class="text-xl font-bold">{{ __('Recent registrations') }}</h2>
        @if ($recentRegistrations->isEmpty())
            <p class="mt-3 text-slate">{{ __('New registrations will appear here.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-slate">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('Institution') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('Reference') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($recentRegistrations as $registration)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $registration->full_name }}</td>
                                <td class="px-4 py-3">{{ $registration->institution?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-receipt">{{ $registration->registration_reference }}</td>
                                <td class="px-4 py-3">{{ $statusLabels[$registration->account_status] ?? $registration->account_status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
