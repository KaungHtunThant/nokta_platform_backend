<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Contracts\RecordRepositoryInterface;
use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The single write funnel for records. Validates input against the entity type's
 * field registry, separates locked columns from the JSON custom-field bag, and
 * persists via the repository (DIP). JSON is the source of truth (Phase 1);
 * the EAV projection + search index are layered on in Phase 4.
 */
final class RecordWriteService
{
    public function __construct(
        private readonly RecordRepositoryInterface $records,
        private readonly FieldGate $fieldGate,
        private readonly FieldProjector $projector,
        private readonly RecordLinkService $links,
    ) {}

    public function create(EntityType $type, RecordInput $input, ?User $actor = null): Record
    {
        $defs = $type->fieldDefinitions()->get();
        $attributes = $this->buildAttributes($defs, $input, $actor, []);
        $attributes['entity_type_id'] = $type->id;

        $record = $this->records->create($attributes);
        $this->projector->sync($record, $defs, $record->data ?? []);
        $this->links->sync($record, $defs, $record->data ?? []);

        return $record;
    }

    public function update(EntityType $type, Record $record, RecordInput $input, ?User $actor = null): Record
    {
        $defs = $type->fieldDefinitions()->get();
        $record = $this->records->update($record, $this->buildAttributes($defs, $input, $actor, $record->data ?? []));
        $this->projector->sync($record, $defs, $record->data ?? []);
        $this->links->sync($record, $defs, $record->data ?? []);

        return $record;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $existing  the record's current custom-field bag (empty on create)
     * @return array<string, mixed>
     */
    private function buildAttributes($defs, RecordInput $input, ?User $actor, array $existing): array
    {
        // Field-level authorization: drop values the actor may not update BEFORE persist, so a forced
        // payload cannot write a denied field. Null actor (seed/internal) = no gating.
        $incoming = $actor !== null
            ? $this->fieldGate->stripUnwritable($input->data, $actor, $defs)
            : $input->data;

        // Overlay the allowed incoming values onto existing data: untouched and denied fields keep
        // their current values (stripping a field must not erase it), while permitted edits apply.
        $data = array_merge($existing, $incoming);

        $this->assertRequiredPresent($defs, $data);

        $clean = $this->sanitizeData($defs, $data);

        // Relation fields reference other records: reject cross-tenant / wrong-type targets before persist.
        $this->links->validate($defs, $clean);

        return [
            'owner_id' => $input->ownerId,
            'stage_id' => $input->stageId,
            'status' => $input->status,
            'data' => $clean,
            // Canonical relations (e.g. contact_id) write a locked column too; [] for non-relation entities.
            ...$this->links->canonicalColumns($defs, $clean),
        ];
    }

    /**
     * Keep only values for keys that are defined fields with the JSON strategy.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeData($defs, array $data): array
    {
        $jsonKeys = $defs
            ->filter(fn (FieldDefinition $d): bool => $d->storage_strategy === 'json')
            ->pluck('key')
            ->all();

        return array_intersect_key($data, array_flip($jsonKeys));
    }

    /**
     * @param  Collection<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $data
     */
    private function assertRequiredPresent($defs, array $data): void
    {
        $missing = $defs
            ->filter(fn (FieldDefinition $d): bool => $d->isRequired())
            ->filter(fn (FieldDefinition $d): bool => ! isset($data[$d->key]) || $data[$d->key] === '')
            ->mapWithKeys(fn (FieldDefinition $d): array => ["data.{$d->key}" => "The {$d->key} field is required."])
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }
}
