<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\FieldDefinition;

/**
 * Evaluates a `computed` field's expression against a record's custom-field bag (ARCHITECTURE §2).
 * Computed fields are derived server-side on write (RecordWriteService) and persisted into
 * records.data like any JSON field, so they are EAV-projectable / filterable / sortable.
 *
 * Implementations MUST be sandboxed — no arbitrary PHP/eval, only the restricted expression grammar.
 * The current binding is a hand-rolled recursive-descent evaluator; it can be swapped for
 * symfony/expression-language behind this contract without touching callers.
 */
interface ComputedFieldEvaluator
{
    /**
     * Evaluate the field's `ui.expression` against $data (field key => value) and cast the result to
     * the field's `ui.result_type` (text|number|date|bool). Returns null on any error / bad expression —
     * a write is never failed because of a computed field (safety net; syntax is validated up front by
     * parses() at definition time).
     *
     * @param  array<string, mixed>  $data
     */
    public function evaluate(FieldDefinition $def, array $data): mixed;

    /**
     * Whether an expression is syntactically valid. Used at field-definition create/update time to
     * reject unparseable expressions with a 422 (fast feedback for authors).
     */
    public function parses(string $expression): bool;
}
