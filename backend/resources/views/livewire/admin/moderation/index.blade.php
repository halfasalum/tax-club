@php
    use App\Support\SafetyDesk;

    $views = ['open' => __('Open'), 'safeguarding' => __('Safeguarding'), 'held' => __('Held by the filter'), 'mine' => __('Assigned to me'), 'closed' => __('Closed')];
    if (! $canSafeguard) unset($views['safeguarding']);
    $categories = SafetyDesk::categoryLabels();
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('Moderation') }}</h1>
            <p class="mt-2 max-w-[65ch] text-lg text-slate">{{ __('Reports from members and posts held by the filter. Urgent and safeguarding cases come first.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->hasControl('restrict_users'))<a wire:navigate href="{{ route('admin.moderation.restrictions') }}" class="btn-ghost">{{ __('Restrictions') }}</a>@endif
            @if (auth()->user()->hasControl('view_safety_reports'))<a wire:navigate href="{{ route('admin.moderation.report') }}" class="btn-ghost">{{ __('Safety report') }}</a>@endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-1 rounded-2xl bg-white p-1.5 shadow-sm" role="tablist" aria-label="{{ __('Status') }}">
        @foreach ($views as $value => $label)
            <button type="button" role="tab" wire:click="$set('view', '{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                    @class(['rounded-xl px-4 py-2 font-extrabold transition-colors', 'bg-ink text-brand' => $view === $value, 'text-slate hover:bg-brand-wash hover:text-ink' => $view !== $value])>
                {{ $label }}@if (($counts[$value] ?? 0) > 0)<span @class(['ml-1 rounded-full px-2 text-sm', 'bg-danger text-white' => $value === 'safeguarding', 'bg-brand text-ink' => $value !== 'safeguarding'])>{{ $counts[$value] }}</span>@endif
            </button>
        @endforeach
    </div>
    <select wire:model.live="category" class="field mt-5 md:max-w-xs" aria-label="{{ __('Category') }}">
        <option value="">{{ __('All categories') }}</option>
        @foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
    </select>

    <div class="mt-6 overflow-x-auto rounded-2xl border-2 border-line bg-white" wire:loading.class="opacity-60">
        <table class="w-full min-w-[52rem] text-left">
            <thead class="border-b-2 border-line bg-mist text-sm">
                <tr>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Case') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Priority') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('About') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-extrabold">{{ __('Reports') }}</th>
                    <th scope="col" class="px-4 py-3 font-extrabold">{{ __('Assigned to') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($this->cases as $case)
                    @php($subject = $describe($case))
                    <tr wire:key="case-{{ $case->case_id }}" class="hover:bg-brand-wash/50">
                        <td class="px-4 py-3">
                            <a wire:navigate href="{{ route('admin.moderation.show', $case) }}" class="font-black hover:underline">{{ $categories[$case->category] ?? $case->category }}</a>
                            <span class="block text-sm text-slate"><span class="font-receipt">{{ $case->reference }}</span> · {{ $case->created_at->diffForHumans() }}@if ($case->source === 'filter') · {{ __('filter') }}@endif</span>
                        </td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-extrabold', 'bg-danger text-white' => in_array($case->priority, ['urgent', 'high'], true), 'bg-mist' => ! in_array($case->priority, ['urgent', 'high'], true)])>{{ \App\Support\FeedbackDesk::priorityLabels()[$case->priority] ?? $case->priority }}</span>
                            @if ($case->safeguarding)<span class="mt-1 block text-xs font-extrabold text-danger">{{ __('Safeguarding') }}</span>@endif
                            @if ($case->involves_minor)<span class="mt-1 block text-xs font-extrabold">{{ __('Under 18 involved') }}</span>@endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-bold">{{ $subject['label'] }}</span>
                            @if ($subject['excerpt'])<span class="block text-sm text-slate">{{ \Illuminate\Support\Str::limit($subject['excerpt'], 90) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $case->reports_count }}</td>
                        <td class="px-4 py-3">{{ $case->assignee?->full_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-lg font-bold text-slate">{{ __('Nothing waiting. Well done!') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $this->cases->links() }}</div>
</div>
