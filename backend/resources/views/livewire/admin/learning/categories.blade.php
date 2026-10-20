<div class="mx-auto max-w-5xl">
    <a wire:navigate href="{{ route('admin.learning.index') }}" class="link">← {{ __('Learning content') }}</a>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Categories') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Members browse lessons and quizzes by category.') }}</p>

    @if ($statusMessage)
        <p role="status" class="mt-6 rounded-2xl bg-savanna px-5 py-4 font-extrabold text-white">{{ $statusMessage }}</p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <ul class="divide-y divide-line self-start rounded-2xl border-2 border-line bg-white">
            @forelse ($categories as $category)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="cat-{{ $category->id }}">
                    <div>
                        <p class="font-black">{{ $category->name_sw }} <span class="font-semibold text-slate">/ {{ $category->name_en }}</span></p>
                        <p class="text-sm text-slate">{{ trans_choice(':count lesson|:count lessons', $category->contents_count, ['count' => $category->contents_count]) }}@unless ($category->is_active) · {{ __('Hidden') }}@endunless</p>
                    </div>
                    <button type="button" wire:click="edit({{ $category->id }})" class="btn-ghost py-1.5">{{ __('Edit') }}</button>
                </li>
            @empty
                <li class="p-8 text-center font-bold text-slate">{{ __('No categories yet.') }}</li>
            @endforelse
        </ul>

        <form wire:submit="save" class="space-y-4 self-start rounded-3xl border-2 border-line bg-white p-6">
            <h2 class="text-xl font-black">{{ $editingId ? __('Edit category') : __('Add a category') }}</h2>
            <div>
                <label for="name_sw" class="block font-bold">{{ __('Name in Kiswahili') }}</label>
                <input wire:model="name_sw" id="name_sw" type="text" @class(['field', 'field-error' => $errors->has('name_sw')])>
                @error('name_sw')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="name_en" class="block font-bold">{{ __('Name in English') }}</label>
                <input wire:model="name_en" id="name_en" type="text" @class(['field', 'field-error' => $errors->has('name_en')])>
                @error('name_en')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="block font-bold">{{ __('Description') }}</label>
                <textarea wire:model="description" id="description" rows="2" class="field"></textarea>
            </div>
            <div class="flex items-end gap-4">
                <div class="w-28">
                    <label for="sort_order" class="block font-bold">{{ __('Order') }}</label>
                    <input wire:model="sort_order" id="sort_order" type="number" min="0" class="field">
                </div>
                <label class="mb-3 flex items-center gap-2 font-bold"><input type="checkbox" wire:model="is_active" class="size-4 accent-ink"> {{ __('Visible to members') }}</label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
                @if ($editingId)<button type="button" wire:click="cancel" class="btn-ghost">{{ __('Cancel') }}</button>@endif
            </div>
        </form>
    </div>
</div>
