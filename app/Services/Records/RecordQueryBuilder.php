<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Models\Record;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Translates a filter DSL ({field, op, value}[]) + an optional sort into an Eloquent query over
 * records (ARCHITECTURE §3), routing each field to the right strategy:
 *  - LOCKED COLUMN  → WHERE / ORDER BY on the real records column (fastest);
 *  - EAV (filterable/sortable field) → constrain via record_values typed slot (indexed);
 *  - JSON (everything else) → JSON path on records.data (correct, but unindexed — flagged slow).
 *
 * Tenant scope is applied by the Record global scope; this builder never removes it.
 */
final class RecordQueryBuilder
{
    /** Real, always-indexed columns on `records` that a filter/sort may target directly. */
    private const LOCKED_COLUMNS = [
        'id', 'stage_id', 'owner_id', 'assignee_id', 'contact_id', 'pipeline_id',
        'status', 'position', 'is_locked', 'created_at', 'updated_at',
    ];

    /**
     * @param  list<array{field: string, op: string, value: mixed}>  $filters
     * @param  array{field: string, dir?: string}|null  $sort
     * @return Builder<Record>
     */
    public function for(EntityType $type, array $filters = [], ?array $sort = null): Builder
    {
        $defs = $this->defs($type);
        $query = $this->filtered($type, $filters);

        $this->applySort($query, $defs, $sort);

        return $query;
    }

    /**
     * Filters-only query (no sort). The board uses this and applies its own per-column position order.
     *
     * @param  list<array{field: string, op: string, value: mixed}>  $filters
     * @return Builder<Record>
     */
    public function filtered(EntityType $type, array $filters = []): Builder
    {
        $defs = $this->defs($type);
        $query = Record::query()->where('entity_type_id', $type->id);

        foreach ($filters as $filter) {
            $this->applyFilter($query, $defs, $filter);
        }

        return $query;
    }

    /** @return Collection<string, FieldDefinition> */
    private function defs(EntityType $type): Collection
    {
        return $type->fieldDefinitions()->get()->keyBy('key');
    }

    /**
     * @param  Builder<Record>  $query
     * @param  Collection<string, FieldDefinition>  $defs
     * @param  array{field: string, op: string, value: mixed}  $filter
     */
    private function applyFilter(Builder $query, Collection $defs, array $filter): void
    {
        $field = $filter['field'];
        $op = $filter['op'];
        $value = $filter['value'];

        if (in_array($field, self::LOCKED_COLUMNS, true)) {
            $this->applyOp($query, "records.{$field}", $op, $value);

            return;
        }

        $def = $defs->get($field);
        if (! $def instanceof FieldDefinition) {
            return; // unknown field — ignore rather than error
        }

        if ($this->isProjected($def)) {
            $slot = $this->slotColumn($def->type);
            $query->whereHas('values', function (BuilderContract $sub) use ($def, $slot, $op, $value): void {
                $sub->where('field_definition_id', $def->id);
                $this->applyOp($sub, $slot, $op, $value);
            });

            return;
        }

        // JSON strategy — correct but unindexed (slow at scale; cap filterable JSON fields in Phase 7).
        $this->applyOp($query, "data->{$field}", $op, $value);
    }

    /**
     * @param  Builder<Record>  $query
     * @param  Collection<string, FieldDefinition>  $defs
     * @param  array{field: string, dir?: string}|null  $sort
     */
    private function applySort(Builder $query, Collection $defs, ?array $sort): void
    {
        if ($sort === null) {
            $query->latest('id');

            return;
        }

        $field = $sort['field'];
        $dir = ($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($field, self::LOCKED_COLUMNS, true)) {
            $query->orderBy("records.{$field}", $dir);

            return;
        }

        $def = $defs->get($field);
        if (! $def instanceof FieldDefinition) {
            $query->latest('id');

            return;
        }

        if ($this->isProjected($def)) {
            // LEFT JOIN the projection so records without a value still sort (as NULL).
            $slot = $this->slotColumn($def->type);
            $query->leftJoin('record_values as rv_sort', function ($join) use ($def): void {
                $join->on('rv_sort.record_id', '=', 'records.id')
                    ->where('rv_sort.field_definition_id', '=', $def->id);
            })
                ->select('records.*')
                ->orderBy("rv_sort.{$slot}", $dir);

            return;
        }

        $query->orderBy("data->{$field}", $dir);
    }

    private function isProjected(FieldDefinition $def): bool
    {
        return $def->is_filterable || $def->is_sortable;
    }

    private function slotColumn(string $type): string
    {
        return match ($type) {
            'number', 'decimal', 'money' => 'value_number',
            'bool', 'checkbox' => 'value_bool',
            'date', 'datetime' => 'value_date',
            default => 'value_string',
        };
    }

    /**
     * @param  Builder<Record>|BuilderContract  $query
     */
    private function applyOp(Builder|BuilderContract $query, string $column, string $op, mixed $value): void
    {
        match ($op) {
            'eq' => $query->where($column, '=', $value),
            'ne' => $query->where($column, '!=', $value),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'in' => $query->whereIn($column, is_array($value) ? $value : [$value]),
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            default => null, // unknown op — no-op
        };
    }
}
