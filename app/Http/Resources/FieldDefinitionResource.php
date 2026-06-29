<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FieldDefinition;
use App\Support\Translate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FieldDefinition
 */
class FieldDefinitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => Translate::label($this->label),
            'help' => $this->help !== null ? Translate::label($this->help) : null,
            'validation' => $this->validation ?? [],
            'ui' => $this->ui ?? [],
            'default_value' => $this->default_value,
            'position' => $this->position,
            // Phase 4 — drive filter/sort/report UI off these capability flags.
            'is_filterable' => $this->is_filterable,
            'is_sortable' => $this->is_sortable,
            'is_reportable' => $this->is_reportable,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o): array => [
                'key' => $o->key,
                'label' => Translate::label($o->label),
                'color' => $o->color,
            ])->all()),
        ];
    }
}
