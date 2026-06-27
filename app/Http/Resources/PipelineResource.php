<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pipeline;
use App\Support\Translate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pipeline
 */
class PipelineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => Translate::label($this->label),
            'position' => $this->position,
        ];
    }
}
