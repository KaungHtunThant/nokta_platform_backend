<?php

declare(strict_types=1);

namespace App\Rules\Stage;

use App\Contracts\StageRuleEvaluator;
use App\DTOs\StageMoveContext;

/**
 * `allow_backward`: when value is explicitly false, a move to an earlier stage (lower position)
 * is rejected.
 */
final class AllowBackwardRule implements StageRuleEvaluator
{
    public function key(): string
    {
        return 'allow_backward';
    }

    public function evaluate(StageMoveContext $context, mixed $value): ?string
    {
        if ($value === false && $context->isBackward()) {
            return 'Cannot move backward to "'.$context->toStage->key.'": backward moves are not allowed.';
        }

        return null;
    }
}
