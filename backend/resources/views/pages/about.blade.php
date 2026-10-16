<x-layouts.public
    :title="__('About Tax Club')"
    :description="__('What TRA Tax Clubs are, who can join, and how patrons and TRA coordinators support members in schools, colleges and universities.')"
>
    <div class="mx-auto max-w-3xl px-4 pt-14 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ __('About Tax Club') }}</h1>
        <p class="mt-6 text-xl leading-relaxed text-slate">
            {{ __('Tax Clubs are student clubs supported by the Tanzania Revenue Authority. Members learn how taxes are collected and spent, why receipts matter, and how a fair tax system helps the country grow.') }}
        </p>

        <h2 class="mt-14 text-2xl font-extrabold">{{ __('Who can join') }}</h2>
        <ul class="mt-4 space-y-3 text-lg leading-relaxed">
            <li>{{ __('Students in secondary schools, colleges and universities with a registered Tax Club.') }}</li>
            <li>{{ __('Former members, who continue as Tax Club alumni after they finish their studies.') }}</li>
        </ul>

        <h2 class="mt-14 text-2xl font-extrabold">{{ __('What members do') }}</h2>
        <ul class="mt-4 space-y-3 text-lg leading-relaxed">
            <li>{{ __('Take short lessons and quizzes on taxation, financial literacy and entrepreneurship.') }}</li>
            <li>{{ __('Take part in competitions, campaigns and events in their region.') }}</li>
            <li>{{ __('Ask TRA experts questions and find internships and other opportunities.') }}</li>
        </ul>

        <h2 id="patrons" class="mt-14 scroll-mt-8 text-2xl font-extrabold">{{ __('For patrons and schools') }}</h2>
        <p class="mt-4 text-lg leading-relaxed">
            {{ __('Every Tax Club has a patron, usually a teacher or lecturer, who coordinates activities at the institution. Patrons confirm student registrations online and work with the TRA Tax Club coordinator for their region. To register your institution, contact the TRA office in your region.') }}
        </p>

        <h2 class="mt-14 text-2xl font-extrabold">{{ __('Keeping members safe') }}</h2>
        <p class="mt-4 text-lg leading-relaxed">
            {{ __('Many members are under 18. Tax Club shows only limited information about members, never shares phone numbers publicly, and reviews community activity.') }}
        </p>
    </div>
</x-layouts.public>
