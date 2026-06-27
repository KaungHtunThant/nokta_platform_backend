<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Contracts\RecordRepositoryInterface;
use App\DTOs\StageMoveContext;
use App\Events\RecordMoved;
use App\Models\Record;
use App\Models\Stage;
use App\Services\Stages\StageTransitionPolicy;

/**
 * The single funnel for stage transitions: validate via the rule-driven policy, persist the new
 * stage/position through the repository (contract), then broadcast. Throws ValidationException
 * (via the policy) when a rule blocks the move — surfaced to the client as a clear reason.
 */
final class RecordMoveService
{
    public function __construct(
        private readonly RecordRepositoryInterface $records,
        private readonly StageTransitionPolicy $policy,
    ) {}

    public function move(Record $record, Stage $toStage, ?string $comment = null, ?float $position = null): Record
    {
        $fromStage = $record->stage_id !== null
            ? Stage::query()->find($record->stage_id)
            : null;

        $context = new StageMoveContext(
            record: $record,
            fromStage: $fromStage,
            toStage: $toStage->loadMissing('rules'),
            comment: $comment,
        );

        $this->policy->assert($context); // throws ValidationException on rejection

        $updated = $this->records->update($record, [
            'stage_id' => $toStage->id,
            'pipeline_id' => $toStage->pipeline_id,
            'position' => $position ?? $record->position,
        ]);

        $updated->loadMissing('entityType');
        event(new RecordMoved($updated, $fromStage?->id, $toStage->id));

        return $updated;
    }
}
