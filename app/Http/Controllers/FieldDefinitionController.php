<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Fields\StoreFieldDefinitionRequest;
use App\Http\Resources\FieldDefinitionResource;
use App\Models\EntityType;
use App\Models\FieldDefinition;
use Illuminate\Http\JsonResponse;

final class FieldDefinitionController extends Controller
{
    public function store(StoreFieldDefinitionRequest $request, string $entityTypeKey): JsonResponse
    {
        $type = EntityType::query()->where('key', $entityTypeKey)->firstOrFail();

        /** @var FieldDefinition $field */
        $field = $type->fieldDefinitions()->create([
            'key' => $request->string('key')->value(),
            'type' => $request->string('type')->value(),
            'label' => $request->input('label'),
            'validation' => $request->input('validation'),
            'ui' => $request->input('ui'),
            'position' => (int) $request->input('position', 0),
            'storage_strategy' => $request->input('storage_strategy', 'json'),
        ]);

        return FieldDefinitionResource::make($field)->response()->setStatusCode(201);
    }
}
