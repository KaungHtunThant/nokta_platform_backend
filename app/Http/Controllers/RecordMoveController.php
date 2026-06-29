<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Records\MoveRecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\Record;
use App\Models\Stage;
use App\Models\User;
use App\Services\Records\RecordMoveService;

/**
 * Thin controller: resolve the target stage, delegate to the move service (which validates via the
 * rule-driven policy and broadcasts), return the updated record. Policy rejections surface as 422.
 */
final class RecordMoveController extends Controller
{
    public function __construct(private readonly RecordMoveService $mover) {}

    public function store(MoveRecordRequest $request, Record $record): RecordResource
    {
        /** @var Stage $stage */
        $stage = Stage::query()->findOrFail($request->integer('stage_id'));

        /** @var User $actor */
        $actor = $request->user();

        $moved = $this->mover->move($record, $stage, $request->comment(), $request->position(), $actor);

        return RecordResource::make($moved->load('entityType'));
    }
}
