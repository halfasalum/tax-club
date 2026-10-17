<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created, updated and deleted events with before/after values.
 *
 * Models may define:
 *  - $auditIgnore: attributes not worth recording (e.g. counters)
 *  - $auditRedact: attributes recorded as "[changed]" without the value
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Audit::record(Audit::typeOf($model).'.created', $model, after: $model->auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changed = $model->auditValues($model->getChanges());
            if ($changed === []) {
                return;
            }

            $before = array_intersect_key($model->auditValues($model->getOriginal()), $changed);
            Audit::record(Audit::typeOf($model).'.updated', $model, $before, $changed);
        });

        static::deleted(function (Model $model) {
            Audit::record(Audit::typeOf($model).'.deleted', $model, before: $model->auditValues($model->getOriginal()));
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        $ignore = [...($this->auditIgnore ?? []), 'created_at', 'updated_at', 'remember_token'];
        $redact = [...($this->auditRedact ?? []), ...$this->getHidden()];

        $values = array_diff_key($values, array_flip($ignore));

        foreach ($values as $key => $value) {
            if (in_array($key, $redact, true)) {
                $values[$key] = $value === null ? null : Audit::REDACTED;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $values;
    }
}
