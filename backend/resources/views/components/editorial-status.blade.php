@props(['status'])
@php
    $styles = [
        'draft' => ['bg-mist text-slate', __('Draft')],
        'in_review' => ['bg-brand text-ink', __('Waiting for review')],
        'returned' => ['bg-[#fef3f2] text-danger', __('Returned for changes')],
        'rejected' => ['bg-[#fef3f2] text-danger', __('Rejected')],
        'approved' => ['bg-[#dcfce7] text-savanna', __('Approved')],
        'published' => ['bg-savanna text-white', __('Live')],
        'scheduled' => ['bg-ink text-brand', __('Scheduled')],
        'not_published' => ['bg-mist text-slate', __('Not published')],
        'unpublished' => ['bg-mist text-slate', __('Unpublished')],
        'archived' => ['bg-mist text-slate', __('Archived')],
        'closed' => ['bg-mist text-slate', __('Closed')],
        'postponed' => ['bg-[#fff7ed] text-[#9a3412]', __('Postponed')],
        'cancelled' => ['bg-[#fef3f2] text-danger', __('Cancelled')],
        'happening' => ['bg-savanna text-white', __('Happening now')],
        'ended' => ['bg-ink text-white', __('Ended')],
        'withdrawn' => ['bg-[#fef3f2] text-danger', __('Withdrawn')],
        'sending' => ['bg-ink text-brand', __('Sending')],
        'sent' => ['bg-savanna text-white', __('Sent')],
    ];
    [$classes, $label] = $styles[$status] ?? ['bg-mist', $status];
@endphp
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-extrabold', $classes]) }}>{{ $label }}</span>
