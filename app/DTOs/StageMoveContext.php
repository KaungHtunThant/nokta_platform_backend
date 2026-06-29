<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Record;
use App\Models\Stage;
use App\Models\User;

/**
 * Immutable carrier describing a proposed stage transition. Passed to each stage-rule evaluator and
 * to the per-stage access gate. Plain value object — it wraps live models for in-process use.
 * `actor` is the user attempting the move (null for internal/seed moves, which skip access checks).
 */
final class StageMoveContext
{
    public function __construct(
        public readonly Record $record,
        public readonly ?Stage $fromStage,
        public readonly Stage $toStage,
        public readonly ?string $comment,
        public readonly ?User $actor = null,
    ) {}

    public function isBackward(): bool
    {
        return $this->fromStage !== null && $this->toStage->position < $this->fromStage->position;
    }
}
