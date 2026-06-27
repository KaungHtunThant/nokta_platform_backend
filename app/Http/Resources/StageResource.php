<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Stage;
use App\Support\Translate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stage
 */
class StageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => Translate::label($this->label),
            'color' => $this->color,
            'position' => $this->position,
            'is_initial' => $this->is_initial,
            'is_won' => $this->is_won,
            'is_lost' => $this->is_lost,
            'rules' => $this->whenLoaded('rules', fn () => $this->rules
                ->map(fn ($rule): array => ['rule' => $rule->rule, 'value' => $rule->value])
                ->all()),
        ];
    }
}
