<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\EntityTypeSchemaResource;
use App\Models\EntityType;

final class SchemaController extends Controller
{
    public function show(string $entityTypeKey): EntityTypeSchemaResource
    {
        $type = EntityType::query()
            ->where('key', $entityTypeKey)
            ->with(['fieldDefinitions.options'])
            ->firstOrFail();

        return EntityTypeSchemaResource::make($type);
    }
}
