<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Contracts\RecordRepositoryInterface;
use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Models\Record;
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
    public function __construct(private readonly RecordRepositoryInterface $records) {}

    public function create(EntityType $type, RecordInput $input): Record
    {
        $attributes = $this->buildAttributes($type, $input);
        $attributes['entity_type_id'] = $type->id;

        return $this->records->create($attributes);
    }

    public function update(EntityType $type, Record $record, RecordInput $input): Record
    {
        return $this->records->update($record, $this->buildAttributes($type, $input));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(EntityType $type, RecordInput $input): array
    {
        $defs = $type->fieldDefinitions()->get();

        $this->assertRequiredPresent($defs, $input->data);

        return [
            'owner_id' => $input->ownerId,
            'stage_id' => $input->stageId,
            'status' => $input->status,
            'data' => $this->sanitizeData($defs, $input->data),
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
            ->filter(fn (FieldDefinition $d): bool => ! isset($data[$d->key]) || $data[$d->key] === '' || $data[$d->key] === null)
            ->mapWithKeys(fn (FieldDefinition $d): array => ["data.{$d->key}" => "The {$d->key} field is required."])
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }
}
