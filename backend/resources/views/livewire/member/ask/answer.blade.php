<div class="mx-auto max-w-3xl">
    <a wire:navigate href="{{ route('member.ask') }}" class="link">← {{ __('Ask TRA') }}</a>
    <article class="mt-4 rounded-3xl border-2 border-line bg-white p-6 sm:p-8">
        @if ($question->category)<p class="text-sm font-extrabold text-slate">{{ $question->category->name() }}</p>@endif
        <h1 class="mt-1 text-2xl font-black leading-snug sm:text-3xl">{{ $question->public_question }}</h1>
        <div class="mt-4 whitespace-pre-line text-lg leading-relaxed">{{ $question->public_answer }}</div>
        <p class="mt-6 flex flex-wrap items-center gap-4 text-sm text-slate">
            <span>{{ __('Answered by TRA · :date', ['date' => $question->published_at->translatedFormat('j M Y')]) }}</span>
            <livewire:safety.report-button type="question" :subject-id="$question->question_id" />
        </p>
    </article>
    <p class="mt-6"><a wire:navigate href="{{ route('member.ask') }}" class="btn-primary">{{ __('Ask your own question') }}</a></p>
</div>
