<?php

declare(strict_types=1);

use App\Models\FieldDefinition;
use App\Services\Records\SafeExpressionEvaluator;

/**
 * Phase 7b — the sandboxed expression engine behind the `computed` field type. Pure unit tests (no DB):
 * arithmetic/logic/string grammar, whitelisted functions, null-safety, and that nothing escapes the
 * sandbox (unknown identifiers → null, unknown functions / bad syntax → not parseable).
 */
function evalExpr(string $expression, array $data = [], string $resultType = 'text'): mixed
{
    $def = new FieldDefinition(['key' => 'c', 'type' => 'computed', 'ui' => ['expression' => $expression, 'result_type' => $resultType]]);

    return (new SafeExpressionEvaluator)->evaluate($def, $data);
}

it('concatenates with ~ and concat()', function () {
    expect(evalExpr('first ~ " " ~ last', ['first' => 'Ada', 'last' => 'Lovelace']))->toBe('Ada Lovelace')
        ->and(evalExpr('concat(first, " ", last)', ['first' => 'Grace', 'last' => 'Hopper']))->toBe('Grace Hopper');
});

it('evaluates arithmetic with correct precedence', function () {
    expect(evalExpr('2 + 3 * 4', [], 'number'))->toBe(14.0)
        ->and(evalExpr('(2 + 3) * 4', [], 'number'))->toBe(20.0)
        ->and(evalExpr('qty * price', ['qty' => 3, 'price' => 2.5], 'number'))->toBe(7.5);
});

it('evaluates comparisons, logic and ternary', function () {
    expect(evalExpr('budget >= 1000 ? "high" : "low"', ['budget' => 1500]))->toBe('high')
        ->and(evalExpr('budget >= 1000 ? "high" : "low"', ['budget' => 500]))->toBe('low')
        ->and(evalExpr('a && b', ['a' => true, 'b' => false], 'bool'))->toBeFalse()
        ->and(evalExpr('a || b', ['a' => false, 'b' => true], 'bool'))->toBeTrue()
        ->and(evalExpr('!done', ['done' => false], 'bool'))->toBeTrue();
});

it('treats unknown identifiers as null (missing field values)', function () {
    // `budget` is absent → null; null >= 1000 is false → "low". No error.
    expect(evalExpr('budget >= 1000 ? "high" : "low"', []))->toBe('low')
        ->and(evalExpr('coalesce(nickname, name, "n/a")', ['name' => 'Bob']))->toBe('Bob')
        ->and(evalExpr('coalesce(nickname, name, "n/a")', []))->toBe('n/a');
});

it('supports whitelisted string/number functions', function () {
    expect(evalExpr('upper(name)', ['name' => 'ab']))->toBe('AB')
        ->and(evalExpr('lower(name)', ['name' => 'AB']))->toBe('ab')
        ->and(evalExpr('length(name)', ['name' => 'hello'], 'number'))->toBe(5.0)
        ->and(evalExpr('round(x, 1)', ['x' => 3.14159], 'number'))->toBe(3.1)
        ->and(evalExpr('abs(x)', ['x' => -4], 'number'))->toBe(4.0)
        ->and(evalExpr('max(a, b, c)', ['a' => 1, 'b' => 9, 'c' => 4], 'number'))->toBe(9.0);
});

it('casts the result to the declared result_type', function () {
    expect(evalExpr('1 + 1', [], 'number'))->toBe(2.0)
        ->and(evalExpr('1 + 1', [], 'text'))->toBe('2')
        ->and(evalExpr('budget >= 1000', ['budget' => 2000], 'bool'))->toBeTrue();
});

it('returns null on a bad expression instead of throwing', function () {
    expect(evalExpr('1 +'))->toBeNull()
        ->and(evalExpr('system("rm -rf /")'))->toBeNull()  // unknown function → null
        ->and(evalExpr('1 / 0', [], 'number'))->toBeNull();
});

it('rejects unparseable / unsafe expressions via parses()', function () {
    $e = new SafeExpressionEvaluator;

    expect($e->parses('budget >= 1000 ? "high" : "low"'))->toBeTrue()
        ->and($e->parses('concat(a, b)'))->toBeTrue()
        ->and($e->parses('1 +'))->toBeFalse()
        ->and($e->parses(')('))->toBeFalse()
        ->and($e->parses('a $ b'))->toBeFalse();  // illegal character
});
