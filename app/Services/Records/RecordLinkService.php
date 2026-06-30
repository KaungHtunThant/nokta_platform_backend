<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\FieldDefinition;
use App\Models\Record;
use App\Models\RecordLink;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Cross-record relations (ARCHITECTURE §1, Phase 6). A `relation` field's value is a target record id
 * (or a list of ids for a multi-relation); the JSON bag is the source of truth and this service mirrors
 * those values into `record_links` so the link is traversable from either side and queryable. Some
 * relation fields also declare a canonical locked column (e.g. contact_id) which the write service sets.
 *
 * All targets must be same-tenant (the Record global scope guarantees a cross-tenant id resolves to
 * null here) and, when the field declares a `target_entity_type`, of that entity type.
 */
final class RecordLinkService
{
    /**
     * Assert every relation-field value in $data references an existing same-tenant record of the
     * expected entity type. Runs BEFORE persist so a bad reference never creates a half-written record.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     */
    public function validate(Collection $defs, array $data): void
    {
        foreach ($this->relationFields($defs) as $def) {
            $expected = $this->targetEntityType($def);

            foreach ($this->idsFor($def, $data) as $targetId) {
                /** @var Record|null $target */
                $target = Record::query()->with('entityType')->find($targetId);

                if ($target === null) {
                    throw ValidationException::withMessages([
                        "data.{$def->key}" => "Linked record {$targetId} was not found in this tenant.",
                    ]);
                }

                if ($expected !== null && $target->entityType?->key !== $expected) {
                    throw ValidationException::withMessages([
                        "data.{$def->key}" => "Linked record must be of type {$expected}.",
                    ]);
                }
            }
        }
    }

    /**
     * Mirror relation-field values into `record_links` (idempotent): upsert a link per target and prune
     * links for this relation_key that the current value no longer references.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     */
    public function sync(Record $record, Collection $defs, array $data): void
    {
        foreach ($this->relationFields($defs) as $def) {
            $targetIds = $this->idsFor($def, $data);

            RecordLink::query()
                ->where('from_record_id', $record->id)
                ->where('relation_key', $def->key)
                ->when($targetIds !== [], fn ($q) => $q->whereNotIn('to_record_id', $targetIds))
                ->delete();

            foreach ($targetIds as $position => $targetId) {
                RecordLink::query()->updateOrCreate(
                    ['from_record_id' => $record->id, 'to_record_id' => $targetId, 'relation_key' => $def->key],
                    ['tenant_id' => $record->tenant_id, 'position' => $position],
                );
            }
        }
    }

    /**
     * Locked-column values to write alongside the JSON bag, derived from relation fields that declare a
     * `canonical_column` (e.g. contact_id). Returns [] when no relation field is canonical, so non-relation
     * entities are never touched.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    public function canonicalColumns(Collection $defs, array $data): array
    {
        $columns = [];

        foreach ($this->relationFields($defs) as $def) {
            $column = $def->ui['canonical_column'] ?? null;
            if (is_string($column) && $column !== '') {
                $ids = $this->idsFor($def, $data);
                $columns[$column] = $ids[0] ?? null;
            }
        }

        return $columns;
    }

    /**
     * Related records for a record, in either direction, optionally narrowed to one relation_key.
     *
     * @return Collection<int, Record>
     */
    public function relatedTo(Record $record, ?string $relationKey = null): Collection
    {
        $links = RecordLink::query()
            ->where(function ($q) use ($record): void {
                $q->where('from_record_id', $record->id)->orWhere('to_record_id', $record->id);
            })
            ->when($relationKey !== null, fn ($q) => $q->where('relation_key', $relationKey))
            ->orderBy('position')
            ->get();

        $otherIds = $links
            ->map(fn (RecordLink $l): int => $l->from_record_id === $record->id ? $l->to_record_id : $l->from_record_id)
            ->unique()
            ->values()
            ->all();

        if ($otherIds === []) {
            return collect();
        }

        return Record::query()->with('entityType')->whereIn('id', $otherIds)->get();
    }

    public function link(Record $from, int $toId, string $relationKey): RecordLink
    {
        $target = Record::query()->find($toId);
        if ($target === null) {
            throw ValidationException::withMessages([
                'to_record_id' => "Linked record {$toId} was not found in this tenant.",
            ]);
        }

        /** @var RecordLink $link */
        $link = RecordLink::query()->updateOrCreate(
            ['from_record_id' => $from->id, 'to_record_id' => $toId, 'relation_key' => $relationKey],
            ['tenant_id' => $from->tenant_id],
        );

        return $link;
    }

    public function unlink(Record $from, int $toId, string $relationKey): void
    {
        RecordLink::query()
            ->where('from_record_id', $from->id)
            ->where('to_record_id', $toId)
            ->where('relation_key', $relationKey)
            ->delete();
    }

    /**
     * @param  Collection<int, FieldDefinition>  $defs
     * @return Collection<int, FieldDefinition>
     */
    private function relationFields(Collection $defs): Collection
    {
        return $defs->filter(fn (FieldDefinition $d): bool => $d->type === 'relation');
    }

    private function targetEntityType(FieldDefinition $def): ?string
    {
        $value = $def->ui['target_entity_type'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Normalise a relation field's value into a list of target record ids (single or multi).
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function idsFor(FieldDefinition $def, array $data): array
    {
        $value = $data[$def->key] ?? null;

        $raw = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        return array_values(array_filter(
            array_map(static fn ($v): int => (int) $v, $raw),
            static fn (int $id): bool => $id > 0,
        ));
    }
}
