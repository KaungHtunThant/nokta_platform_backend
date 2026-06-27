<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'entity_type' => $this->whenLoaded('entityType', fn () => $this->entityType->key),
            'stage_id' => $this->stage_id,
            'owner_id' => $this->owner_id,
            'status' => $this->status,
            'data' => $this->data ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
