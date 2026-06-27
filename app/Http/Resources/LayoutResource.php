<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Layout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Layout
 */
class LayoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'surface' => $this->surface,
            'key' => $this->key,
            'version' => $this->version,
            'schema_version' => $this->schema_version,
            'schema' => $this->schema,
        ];
    }
}
