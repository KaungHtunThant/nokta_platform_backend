<?php

declare(strict_types=1);

namespace App\Rules\Stage;

use App\Contracts\StageRuleEvaluator;
use App\DTOs\StageMoveContext;

/**
 * `require_fields`: value is a list of field keys that must be present (non-empty) on the record
 * before it may enter the target stage. Generalizes the old hardcoded getMissingDealInfoField.
 */
final class RequireFieldsRule implements StageRuleEvaluator
{
    public function key(): string
    {
        return 'require_fields';
    }

    public function evaluate(StageMoveContext $context, mixed $value): ?string
    {
        $keys = is_array($value) ? $value : [];
        $data = $context->record->data ?? [];

        // isset() is already false for null, so an empty-string check is the only extra case.
        $missing = array_values(array_filter(
            $keys,
            fn (mixed $key): bool => ! isset($data[$key]) || $data[$key] === '',
        ));

        if ($missing === []) {
            return null;
        }

        return 'Cannot move to "'.$context->toStage->key.'": required fields missing: '.implode(', ', $missing).'.';
    }
}
