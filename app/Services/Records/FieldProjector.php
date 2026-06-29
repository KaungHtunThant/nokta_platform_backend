<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\FieldDefinition;
use App\Models\Record;
use App\Models\RecordValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Maintains the EAV projection (record_values) from a record's JSON bag (ARCHITECTURE §3). Called by
 * the write funnel after every persist; idempotent (one upserted row per projected field). JSON is
 * authoritative, so this projection is always rebuildable (see RebuildProjection).
 *
 * A field is projected when it is flagged filterable or sortable. The value lands in the typed slot
 * matching the field type; multi-valued / non-scalar types (multiselect, relation, file) are not
 * EAV-projected (they stay in JSON / the search index).
 */
final class FieldProjector
{
    /**
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     */
    public function sync(Record $record, Collection $defs, array $data): void
    {
        foreach ($defs as $def) {
            if (! $this->isProjected($def)) {
                continue;
            }

            RecordValue::query()->updateOrCreate(
                ['record_id' => $record->id, 'field_definition_id' => $def->id],
                $this->slot($def, $data[$def->key] ?? null),
            );
        }
    }

    public function isProjected(FieldDefinition $def): bool
    {
        return $def->is_filterable || $def->is_sortable;
    }

    /**
     * Build the typed value slots for one field. Exactly one slot is non-null (the rest are cleared,
     * so re-projecting after a type/value change leaves no stale slot).
     *
     * @return array<string, mixed>
     */
    private function slot(FieldDefinition $def, mixed $value): array
    {
        $slots = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_bool' => null,
            'value_option_id' => null,
        ];

        if ($value === null || $value === '') {
            return $slots;
        }

        return match ($def->type) {
            'number', 'decimal', 'money' => [...$slots, 'value_number' => (float) $value],
            'bool', 'checkbox' => [...$slots, 'value_bool' => (bool) $value],
            'date', 'datetime' => [...$slots, 'value_date' => $this->toDate($value)],
            default => [...$slots, 'value_string' => (string) $value], // text/select/radio/email/…
        };
    }

    private function toDate(mixed $value): ?Carbon
    {
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
