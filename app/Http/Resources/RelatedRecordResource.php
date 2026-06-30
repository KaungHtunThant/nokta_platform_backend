<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A linked record shown on a detail surface's related-records list (Phase 6): just enough to render a
 * labelled link to the other record — not the full custom-field bag (which has its own field-level
 * read gating on the target's own detail view).
 *
 * @mixin Record
 */
class RelatedRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->whenLoaded('entityType', fn () => $this->entityType?->key),
            'label' => $this->whenLoaded('entityType', fn () => $this->entityType?->titleFor($this->resource)),
        ];
    }
}
