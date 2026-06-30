<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EntityType;
use App\Services\Records\FilterableFieldCap;
use App\Support\Translate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The schema a config-driven client needs to render an entity: its identity +
 * ordered field definitions (with options).
 *
 * @mixin EntityType
 */
class EntityTypeSchemaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => Translate::label($this->label),
            'icon' => $this->icon,
            'supports_pipeline' => $this->supports_pipeline,
            'fields' => FieldDefinitionResource::collection($this->whenLoaded('fieldDefinitions')),
            // Phase 7: per-tenant filterable budget, so the builder can show remaining capacity.
            'limits' => ['filterable_remaining' => app(FilterableFieldCap::class)->remaining()],
        ];
    }
}
