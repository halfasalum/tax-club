<?php

namespace App\Support;

use App\Models\ClubPatron;
use App\Models\Institution;
use App\Models\TaxClub;
use App\Models\User;
use App\Notifications\PatronAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Institution, Tax Club and patron lifecycle (INS-REQ01–05), shared by the
 * Administration Portal, the CSV import and the API.
 */
final class InstitutionRegistry
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'type' => ['required', Rule::in(Institution::CATEGORIES)],
            'region' => ['required', Rule::in(array_values(config('tanzania.regions')))],
            'district' => ['required', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'regex:/^(\+?255|0)[67]\d{8}$/'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ];
    }

    /**
     * An existing institution with the same name in the same region and
     * district, ignoring case, punctuation and repeated spaces.
     */
    public static function findDuplicate(string $name, string $region, string $district, ?string $ignoreId = null): ?Institution
    {
        $key = self::nameKey($name);

        return Institution::query()
            ->where('region', $region)
            ->whereRaw('LOWER(TRIM(district)) = ?', [Str::lower(trim($district))])
            ->when($ignoreId, fn ($query) => $query->where('institution_id', '!=', $ignoreId))
            ->get(['institution_id', 'name', 'code', 'region', 'district'])
            ->first(fn (Institution $institution) => self::nameKey($institution->name) === $key);
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public static function create(array $data, ?User $officer): Institution
    {
        if ($duplicate = self::findDuplicate($data['name'], $data['region'], $data['district'])) {
            throw new InvalidArgumentException(__('This institution is already registered as :name (:code).', ['name' => $duplicate->name, 'code' => $duplicate->code]));
        }

        return DB::transaction(function () use ($data, $officer) {
            $institution = Institution::create([
                ...self::clean($data),
                'code' => self::newCode($data['region'], $data['type']),
                'status' => Institution::STATUS_ACTIVE,
                'created_by' => $officer?->user_id,
            ]);

            self::recordStatus($institution, null, Institution::STATUS_ACTIVE, null, $officer);

            return $institution;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public static function update(Institution $institution, array $data, ?User $officer): Institution
    {
        if ($duplicate = self::findDuplicate($data['name'], $data['region'], $data['district'], $institution->institution_id)) {
            throw new InvalidArgumentException(__('This institution is already registered as :name (:code).', ['name' => $duplicate->name, 'code' => $duplicate->code]));
        }

        // The code identifies the institution in reports and is never changed (INS-REQ02 §4).
        $institution->fill(self::clean($data))->save();

        return $institution;
    }

    public static function changeStatus(Institution $institution, string $status, ?string $reason, ?User $officer): void
    {
        if (! in_array($status, [Institution::STATUS_ACTIVE, Institution::STATUS_INACTIVE], true) || $status === $institution->status) {
            throw new InvalidArgumentException(__('The institution already has this status.'));
        }

        if ($status === Institution::STATUS_INACTIVE && blank($reason)) {
            throw new InvalidArgumentException(__('A reason is required to deactivate an institution.'));
        }

        DB::transaction(function () use ($institution, $status, $reason, $officer) {
            $from = $institution->status;
            $institution->forceFill(['status' => $status, 'status_reason' => $reason ? trim($reason) : null])->save();
            self::recordStatus($institution, $from, $status, $reason, $officer);
        });
    }

    /**
     * Establish the institution's Tax Club. Only one active club per institution.
     *
     * @param  array{name?: ?string, established_on?: ?string}  $data
     */
    public static function establishClub(Institution $institution, array $data, ?User $officer): TaxClub
    {
        if ($institution->taxClubs()->where('status', TaxClub::STATUS_ACTIVE)->exists()) {
            throw new InvalidArgumentException(__('This institution already has an active Tax Club.'));
        }

        return TaxClub::create([
            'institution_id' => $institution->institution_id,
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : $institution->name.' Tax Club',
            'established_on' => $data['established_on'] ?? null,
            'status' => TaxClub::STATUS_ACTIVE,
            'created_by' => $officer?->user_id,
        ]);
    }

    public static function changeClubStatus(TaxClub $club, string $status, ?string $reason): void
    {
        if ($status === TaxClub::STATUS_INACTIVE && blank($reason)) {
            throw new InvalidArgumentException(__('A reason is required to deactivate a Tax Club.'));
        }

        if ($status === TaxClub::STATUS_ACTIVE && TaxClub::where('institution_id', $club->institution_id)
            ->where('status', TaxClub::STATUS_ACTIVE)->where('tax_club_id', '!=', $club->tax_club_id)->exists()) {
            throw new InvalidArgumentException(__('This institution already has an active Tax Club.'));
        }

        $club->forceFill(['status' => $status, 'status_reason' => $reason ? trim($reason) : null])->save();
    }

    /**
     * Assign a patron to a club. The patron is an existing user found by phone
     * or email, or a new staff account. Returns the assignment and, for a new
     * account, the one-time password to hand over.
     *
     * @param  array{phone: string, full_name?: ?string, email?: ?string, starts_on?: ?string}  $data
     * @return array{0: ClubPatron, 1: ?string}
     */
    public static function assignPatron(TaxClub $club, array $data, ?User $officer): array
    {
        if ($club->status !== TaxClub::STATUS_ACTIVE) {
            throw new InvalidArgumentException(__('Patrons can only be assigned to an active Tax Club.'));
        }

        $existing = User::where('phone', MemberRegistration::normalizePhone($data['phone']))->first();

        if ($existing && $club->currentPatrons()->where('user_id', $existing->user_id)->exists()) {
            throw new InvalidArgumentException(__(':name is already a patron of this Tax Club.', ['name' => $existing->full_name]));
        }

        $result = DB::transaction(function () use ($club, $data, $officer) {
            [$patron, $temporaryPassword] = StaffAccounts::findOrCreate(
                $data,
                $club->institution_id,
                __('No account uses this phone number. Enter the patron’s full name to create one.'),
            );

            StaffAccounts::grantRole($patron, 'patron', $officer);

            $assignment = ClubPatron::create([
                'tax_club_id' => $club->tax_club_id,
                'user_id' => $patron->user_id,
                'starts_on' => $data['starts_on'] ?? now()->toDateString(),
                'assigned_by' => $officer?->user_id,
            ]);

            return [$assignment, $temporaryPassword];
        });

        Notify::send($result[0]->user, new PatronAssigned($club->name, $result[1]));

        return $result;
    }

    /**
     * End a patron assignment. The record is kept; activities stay attributed.
     */
    public static function endPatronAssignment(ClubPatron $assignment, string $reason, ?User $officer): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('A reason is required to end a patron assignment.'));
        }

        $assignment->forceFill([
            'ends_on' => now()->toDateString(),
            'end_reason' => trim($reason),
            'ended_by' => $officer?->user_id,
        ])->save();

        // Patrons keep the role only while they still look after a club.
        $stillPatron = ClubPatron::current()->where('user_id', $assignment->user_id)->where('id', '!=', $assignment->id)->exists();

        if (! $stillPatron) {
            StaffAccounts::revokeRole($assignment->user, 'patron', $officer);
        }
    }

    /**
     * Import institutions from CSV with the header:
     * name,category,region,district,contact_name,contact_phone,contact_email
     *
     * @return array{created: int, skipped: list<array{line: int, name: string, reason: string}>}
     */
    public static function import(string $path, ?User $officer): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($column) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), fgetcsv($handle) ?: []);
        $report = ['created' => 0, 'skipped' => []];
        $line = 1;

        if (! in_array('name', $header, true) || ! in_array('region', $header, true)) {
            fclose($handle);

            throw new InvalidArgumentException(__('The file must have a header row with at least the columns name, category, region and district.'));
        }

        $regionsByName = array_change_key_case(array_combine(array_values(config('tanzania.regions')), array_values(config('tanzania.regions'))), CASE_LOWER);

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $values = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $data = [
                'name' => trim((string) ($values['name'] ?? '')),
                'type' => Str::lower(trim((string) ($values['category'] ?? $values['type'] ?? ''))),
                'region' => $regionsByName[Str::lower(trim((string) ($values['region'] ?? '')))] ?? trim((string) ($values['region'] ?? '')),
                'district' => trim((string) ($values['district'] ?? '')),
                'contact_name' => trim((string) ($values['contact_name'] ?? '')) ?: null,
                'contact_phone' => MemberRegistration::compactPhone($values['contact_phone'] ?? '') ?: null,
                'contact_email' => trim((string) ($values['contact_email'] ?? '')) ?: null,
            ];

            $validator = Validator::make($data, self::rules());

            if ($validator->fails()) {
                $report['skipped'][] = ['line' => $line, 'name' => $data['name'], 'reason' => $validator->errors()->first()];

                continue;
            }

            try {
                self::create($validator->validated(), $officer);
                $report['created']++;
            } catch (InvalidArgumentException $exception) {
                $report['skipped'][] = ['line' => $line, 'name' => $data['name'], 'reason' => $exception->getMessage()];
            }
        }

        fclose($handle);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return [
            'name' => preg_replace('/\s+/', ' ', trim($data['name'])),
            'type' => $data['type'],
            'region' => $data['region'],
            'district' => trim($data['district']),
            'contact_name' => filled($data['contact_name'] ?? null) ? trim($data['contact_name']) : null,
            'contact_phone' => filled($data['contact_phone'] ?? null) ? MemberRegistration::normalizePhone($data['contact_phone']) : null,
            'contact_email' => filled($data['contact_email'] ?? null) ? Str::lower(trim($data['contact_email'])) : null,
        ];
    }

    private static function nameKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($name)));
    }

    /**
     * Code such as DSM-S-0042: region, category initial and a sequence.
     */
    private static function newCode(string $region, string $type): string
    {
        $regionCode = array_search($region, config('tanzania.regions'), true) ?: 'TZA';
        $prefix = $regionCode.'-'.Str::upper(substr($type, 0, 1)).'-';

        $last = Institution::where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        do {
            $code = $prefix.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (Institution::where('code', $code)->exists());

        return $code;
    }

    private static function recordStatus(Institution $institution, ?string $from, string $to, ?string $reason, ?User $officer): void
    {
        $institution->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason ? trim($reason) : null,
            'changed_by' => $officer?->user_id,
            'changed_at' => now(),
        ]);
    }
}
