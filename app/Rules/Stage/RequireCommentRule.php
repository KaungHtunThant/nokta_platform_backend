<?php

declare(strict_types=1);

namespace App\Rules\Stage;

use App\Contracts\StageRuleEvaluator;
use App\DTOs\StageMoveContext;

/**
 * `require_comment`: when value is truthy, the move must carry a non-empty comment.
 */
final class RequireCommentRule implements StageRuleEvaluator
{
    public function key(): string
    {
        return 'require_comment';
    }

    public function evaluate(StageMoveContext $context, mixed $value): ?string
    {
        if ($value !== true) {
            return null;
        }

        $comment = $context->comment;

        if ($comment !== null && trim($comment) !== '') {
            return null;
        }

        return 'Cannot move to "'.$context->toStage->key.'": a comment is required.';
    }
}
