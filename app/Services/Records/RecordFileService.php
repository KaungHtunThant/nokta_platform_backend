<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\FieldDefinition;
use App\Models\Record;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * File/media fields (Phase 7). A `file` field's values are attachments on the record, stored via
 * medialibrary in a collection named after the field key — NOT in the JSON bag or the EAV projection
 * (FieldProjector already skips non-scalar types). The media table is the source of truth for files.
 * Field-level write authorization is enforced here (FieldGate), same boundary as JSON field writes.
 */
final class RecordFileService
{
    public function __construct(private readonly FieldGate $fieldGate) {}

    public function attach(Record $record, string $fieldKey, UploadedFile $file, ?User $actor = null): Media
    {
        $def = $record->entityType()->firstOrFail()->fieldDefinitions()->where('key', $fieldKey)->first();

        if (! $def instanceof FieldDefinition || $def->type !== 'file') {
            throw ValidationException::withMessages(['field_key' => "Unknown file field [{$fieldKey}]."]);
        }

        if ($actor !== null && ! $this->fieldGate->canUpdate($actor, $def)) {
            throw ValidationException::withMessages(['field_key' => "You may not write the [{$fieldKey}] field."]);
        }

        return $record->addMedia($file)->toMediaCollection($fieldKey, 'public');
    }

    public function detach(Record $record, int $mediaId): void
    {
        // Scope through the record (tenant-safe): never resolve media by global id.
        $record->media()->whereKey($mediaId)->first()?->delete();
    }
}
