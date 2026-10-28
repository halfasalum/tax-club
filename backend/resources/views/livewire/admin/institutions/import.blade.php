<div class="max-w-3xl">
    <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm font-bold">
        <a wire:navigate href="{{ route('admin.institutions.index') }}" class="link">{{ __('Institutions') }}</a>
    </nav>
    <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ __('Import institutions') }}</h1>
    <p class="mt-2 text-lg text-slate">{{ __('Register many institutions at once from a spreadsheet saved as CSV. Rows that are incomplete or already registered are skipped and listed below.') }}</p>

    <ol class="mt-8 space-y-4">
        <li class="flex gap-4 rounded-2xl border-2 border-line bg-white p-5">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink font-black text-brand" aria-hidden="true">1</span>
            <div>
                <p class="font-black">{{ __('Download the template') }}</p>
                <p class="mt-1 text-slate">{{ __('Columns: name, category (primary, secondary, college or university), region, district, and optional contact_name, contact_phone, contact_email. Region must be one of the 31 regions, spelled as in the list.') }}</p>
                <a href="{{ route('admin.institutions.template') }}" class="link mt-3 inline-block">{{ __('Download CSV template') }}</a>
            </div>
        </li>
        <li class="flex gap-4 rounded-2xl border-2 border-line bg-white p-5">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink font-black text-brand" aria-hidden="true">2</span>
            <form wire:submit="import" class="w-full">
                <label for="file" class="block font-black">{{ __('Upload your file') }}</label>
                <input wire:model="file" id="file" type="file" accept=".csv,text/csv" class="mt-3 block w-full rounded-xl border-2 border-dashed border-line bg-mist p-4 font-semibold file:mr-4 file:rounded-lg file:border-0 file:bg-ink file:px-4 file:py-2 file:font-extrabold file:text-white hover:border-ink">
                <p wire:loading wire:target="file" class="mt-2 font-semibold text-slate">{{ __('Uploading…') }}</p>
                @error('file')<p class="mt-2 font-semibold text-danger" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="btn-brand mt-4" wire:loading.attr="disabled" wire:target="file, import">
                    <span wire:loading.remove wire:target="import">{{ __('Import institutions') }}</span>
                    <span wire:loading wire:target="import">{{ __('Importing…') }}</span>
                </button>
            </form>
        </li>
    </ol>

    @if ($report)
        <section class="mt-8" aria-labelledby="report-heading" role="status">
            <h2 id="report-heading" class="text-2xl font-black">{{ __('Import result') }}</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-savanna p-5 text-white">
                    <p class="font-extrabold">{{ __('Registered') }}</p>
                    <p class="mt-1 text-4xl font-black tabular-nums">{{ number_format($report['created']) }}</p>
                </div>
                <div class="rounded-2xl border-2 border-line bg-white p-5">
                    <p class="font-extrabold text-slate">{{ __('Skipped') }}</p>
                    <p class="mt-1 text-4xl font-black tabular-nums">{{ number_format(count($report['skipped'])) }}</p>
                </div>
            </div>
            @if ($report['skipped'])
                <div class="mt-4 overflow-x-auto rounded-2xl border-2 border-line bg-white">
                    <table class="w-full text-left">
                        <thead class="bg-ink text-white">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Line') }}</th>
                                <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Name') }}</th>
                                <th scope="col" class="px-5 py-3 font-extrabold">{{ __('Reason') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($report['skipped'] as $row)
                                <tr>
                                    <td class="px-5 py-3 tabular-nums">{{ $row['line'] }}</td>
                                    <td class="px-5 py-3 font-semibold">{{ $row['name'] ?: '—' }}</td>
                                    <td class="px-5 py-3 text-slate">{{ $row['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <a wire:navigate href="{{ route('admin.institutions.index') }}" class="btn-primary mt-6">{{ __('View institutions') }}</a>
        </section>
    @endif
</div>
