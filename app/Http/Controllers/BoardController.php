<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\EntityType;
use App\Services\Board\BoardReadService;
use Illuminate\Http\Request;

final class BoardController extends Controller
{
    public function __construct(private readonly BoardReadService $board) {}

    public function show(Request $request, string $entityTypeKey): BoardResource
    {
        $type = EntityType::query()->where('key', $entityTypeKey)->firstOrFail();

        $data = $this->board->forEntityType(
            type: $type,
            pipelineId: $request->integer('pipeline_id') ?: null,
            onlyStageId: $request->integer('stage_id') ?: null,
            page: max(1, $request->integer('page', 1)),
            perPage: min(100, max(1, $request->integer('per_page', 25))),
        );

        return BoardResource::make($data);
    }
}
