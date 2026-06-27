<?php

declare(strict_types=1);

namespace App\Rules\Stage;

use App\Contracts\StageRuleEvaluator;
use App\DTOs\StageMoveContext;

/**
 * `cooldown`: value is a number of seconds that must elapse since the record was last changed
 * before it may move again. Uses updated_at as the last-activity proxy.
 */
final class CooldownRule implements StageRuleEvaluator
{
    public function key(): string
    {
        return 'cooldown';
    }

    public function evaluate(StageMoveContext $context, mixed $value): ?string
    {
        $seconds = is_numeric($value) ? (int) $value : 0;
        $updatedAt = $context->record->updated_at;

        if ($seconds <= 0 || $updatedAt === null) {
            return null;
        }

        $elapsed = now()->diffInSeconds($updatedAt, absolute: true);

        if ($elapsed >= $seconds) {
            return null;
        }

        return 'Cannot move yet: this record must wait '.($seconds - (int) $elapsed).'s before changing stage again.';
    }
}
