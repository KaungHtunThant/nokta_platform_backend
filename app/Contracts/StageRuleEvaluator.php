<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\StageMoveContext;

/**
 * One transition-rule evaluator (Strategy). Keyed by the `stage_rules.rule` value it handles.
 * `evaluate` returns a human-readable rejection reason, or null when the move is permitted.
 * New rule types are added by implementing this and tagging it — no edit to the policy (OCP).
 */
interface StageRuleEvaluator
{
    public function key(): string;

    public function evaluate(StageMoveContext $context, mixed $value): ?string;
}
