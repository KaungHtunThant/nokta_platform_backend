<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Record
 */
class RecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->whenLoaded('entityType', fn () => $this->entityType?->key),
            // Human label (entity_types.config.title_field) — used by relation pickers/displays so the
            // client never has to know which field titles a given entity type.
            'label' => $this->whenLoaded('entityType', fn () => $this->entityType?->titleFor($this->resource)),
            'stage_id' => $this->stage_id,
            'owner_id' => $this->owner_id,
            'status' => $this->status,
            'is_locked' => (bool) $this->is_locked,
            'data' => $this->visibleData($request),
            // Phase 7: file fields → media URLs, keyed by field key (media collection == field key).
            'files' => $this->whenLoaded('media', fn (): array => $this->fileMap($request)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Files grouped by field key (media collection name), filtered to readable fields (PHI). Each entry
     * is a list of {id, name, url}. Media collection names equal field keys, so no field-def lookup needed.
     *
     * @return array<string, list<array{id: int, name: string, url: string}>>
     */
    private function fileMap(Request $request): array
    {
        /** @var list<string>|null $readable */
        $readable = $request->attributes->get('readableFieldKeys');

        $map = [];
        foreach ($this->media as $media) {
            /** @var Media $media */
            $key = $media->collection_name;
            if (is_array($readable) && ! in_array($key, $readable, true)) {
                continue;
            }
            $map[$key][] = ['id' => $media->id, 'name' => $media->file_name, 'url' => $media->getUrl()];
        }

        return $map;
    }

    /**
     * Custom-field bag, filtered to the keys the actor may read. The controller stashes the readable
     * keys on the request (via FieldGate); absent that, the bag is returned whole (internal/no-actor).
     *
     * @return array<string, mixed>
     */
    private function visibleData(Request $request): array
    {
        $data = $this->data ?? [];

        /** @var list<string>|null $readable */
        $readable = $request->attributes->get('readableFieldKeys');

        return is_array($readable)
            ? array_intersect_key($data, array_flip($readable))
            : $data;
    }
}
