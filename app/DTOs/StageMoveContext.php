<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Record;
use App\Models\Stage;

/**
 * Immutable carrier describing a proposed stage transition. Passed to each stage-rule evaluator.
 * Plain value object (not a request/response Data) — it wraps live models for in-process use.
 */
final class StageMoveContext
{
    public function __construct(
        public readonly Record $record,
        public readonly ?Stage $fromStage,
        public readonly Stage $toStage,
        public readonly ?string $comment,
    ) {}

    public function isBackward(): bool
    {
        return $this->fromStage !== null && $this->toStage->position < $this->fromStage->position;
    }
}
