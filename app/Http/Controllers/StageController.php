<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Stages\ReorderStagesRequest;
use App\Http\Requests\Stages\StoreStageRequest;
use App\Http\Resources\StageResource;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StageController extends Controller
{
    public function index(Pipeline $pipeline): AnonymousResourceCollection
    {
        return StageResource::collection($pipeline->stages()->with('rules')->get());
    }

    public function store(StoreStageRequest $request, Pipeline $pipeline): JsonResponse
    {
        /** @var Stage $stage */
        $stage = $pipeline->stages()->create($this->attributes($request));
        $this->syncRules($stage, $request->ruleRows());

        return StageResource::make($stage->load('rules'))->response()->setStatusCode(201);
    }

    public function update(StoreStageRequest $request, Stage $stage): StageResource
    {
        $stage->update($this->attributes($request));
        $this->syncRules($stage, $request->ruleRows());

        return StageResource::make($stage->load('rules'));
    }

    public function destroy(Stage $stage): JsonResponse
    {
        $stage->delete();

        return response()->json(status: 204);
    }

    public function reorder(ReorderStagesRequest $request, Pipeline $pipeline): AnonymousResourceCollection
    {
        foreach ($request->order() as $position => $id) {
            $pipeline->stages()->whereKey($id)->update(['position' => $position]);
        }

        return StageResource::collection($pipeline->stages()->with('rules')->get());
    }

    /** @return array<string, mixed> */
    private function attributes(StoreStageRequest $request): array
    {
        return [
            'key' => $request->string('key')->value(),
            'label' => $request->input('label'),
            'color' => $request->input('color'),
            'position' => (int) $request->input('position', 0),
            'is_initial' => $request->boolean('is_initial'),
            'is_won' => $request->boolean('is_won'),
            'is_lost' => $request->boolean('is_lost'),
        ];
    }

    /** @param list<array{rule: string, value: mixed}> $rules */
    private function syncRules(Stage $stage, array $rules): void
    {
        $stage->rules()->delete();

        foreach ($rules as $row) {
            $stage->rules()->create(['rule' => $row['rule'], 'value' => $row['value']]);
        }
    }
}
