<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\EntityType;
use App\Models\Record;
use Illuminate\Support\Collection;

/**
 * Backs the relation-field record picker (Phase 6): a light, tenant-scoped {id, label} list of records
 * for a target entity type, optionally narrowed by a free-text query against the entity's title field.
 *
 * The title field lives in the JSON bag by convention (entity_types.config.title_field), so the search
 * matches case-insensitively against the extracted JSON value — note MySQL's json_unquote() yields a
 * utf8mb4_bin (case-sensitive) collation, hence the explicit lower() on both sides.
 */
final class RecordPickerService
{
    /**
     * @return Collection<int, array{id: int, label: string, entity_type: string}>
     */
    public function search(EntityType $type, ?string $query = null, int $limit = 20): Collection
    {
        $records = Record::query()
            ->where('entity_type_id', $type->id)
            ->when(
                $query !== null && $query !== '' && $type->titleField() !== null,
                fn ($q) => $q->whereRaw(
                    'lower(json_unquote(json_extract(`data`, ?))) like ?',
                    ['$.'.$type->titleField(), '%'.mb_strtolower((string) $query).'%'],
                ),
            )
            ->latest('id')
            ->limit($limit)
            ->get();

        return $records
            ->map(fn (Record $record): array => [
                'id' => $record->id,
                'label' => $type->titleFor($record),
                'entity_type' => $type->key,
            ])
            ->values();
    }
}
