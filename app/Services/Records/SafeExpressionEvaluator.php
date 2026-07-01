<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Contracts\ComputedFieldEvaluator;
use App\Models\FieldDefinition;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * A small, fully-sandboxed expression evaluator for `computed` fields (ARCHITECTURE §2).
 *
 * It is a recursive-descent parser + tree-walking evaluator over a fixed grammar — NO PHP eval, no
 * function/method calls except a whitelisted set, no property/array access, no I/O. The only inputs are
 * the record's field values (exposed as variables) and literals. This is deliberately more locked-down
 * than a general expression engine: the entire vocabulary is what's written below.
 *
 * Grammar (lowest → highest precedence):
 *   ternary     := logicOr ('?' expr ':' expr)?
 *   logicOr     := logicAnd ('||' logicAnd)*
 *   logicAnd    := equality ('&&' equality)*
 *   equality    := comparison (('==' | '!=') comparison)*
 *   comparison  := additive (('<' | '>' | '<=' | '>=') additive)*
 *   additive    := term (('+' | '-' | '~') term)*        // '~' = string concatenation (EL-style)
 *   term        := unary (('*' | '/' | '%') unary)*
 *   unary       := ('!' | '-') unary | primary
 *   primary     := NUMBER | STRING | 'true' | 'false' | 'null'
 *                | IDENT | IDENT '(' args? ')' | '(' expr ')'
 *
 * Whitelisted functions: concat, coalesce, upper, lower, length, round, abs, min, max.
 * Unknown identifiers evaluate to null (so `budget >= 1000` works even when `budget` is unset), and any
 * runtime/parse error surfaces as null from evaluate() (write-safe). Syntax is validated by parses().
 */
final class SafeExpressionEvaluator implements ComputedFieldEvaluator
{
    private const MAX_LENGTH = 2000;

    /** @var array<string, array<int, mixed>> parsed-AST cache keyed by expression text */
    private array $astCache = [];

    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    public function evaluate(FieldDefinition $def, array $data): mixed
    {
        $ui = $def->ui ?? [];
        $expression = is_string($ui['expression'] ?? null) ? $ui['expression'] : '';
        if ($expression === '') {
            return null;
        }

        try {
            $value = $this->run($this->ast($expression), $data);
        } catch (\Throwable $e) {
            Log::warning('Computed field evaluation failed', ['field' => $def->key, 'error' => $e->getMessage()]);

            return null;
        }

        $resultType = is_string($ui['result_type'] ?? null) ? $ui['result_type'] : 'text';

        return $this->cast($value, $resultType);
    }

    public function parses(string $expression): bool
    {
        try {
            $this->ast($expression);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Coerce the raw evaluated value to the declared result type. Mirrors FieldProjector's typed slots
     * so a computed value filters/sorts like a native field of that type.
     */
    private function cast(mixed $value, string $resultType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($resultType) {
            'number' => is_numeric($value) ? (float) $value : null,
            'bool' => $this->truthy($value),
            'date' => is_scalar($value) ? (string) $value : null,
            default => $this->stringify($value), // text
        };
    }

    // --- parsing ---------------------------------------------------------------------------------

    /**
     * @return array<int, mixed>
     */
    private function ast(string $expression): array
    {
        if (isset($this->astCache[$expression])) {
            return $this->astCache[$expression];
        }

        if (strlen($expression) > self::MAX_LENGTH) {
            throw new RuntimeException('Expression too long.');
        }

        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $ast = $this->parseExpression();

        if ($this->pos !== count($this->tokens)) {
            throw new RuntimeException('Unexpected trailing tokens.');
        }

        return $this->astCache[$expression] = $ast;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;

        while ($i < $len) {
            $c = $s[$i];

            if (ctype_space($c)) {
                $i++;

                continue;
            }

            // two-character operators
            $two = substr($s, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $two];
                $i += 2;

                continue;
            }

            // single-character operators / punctuation
            if (str_contains('+-*/%~<>!?:(),', $c)) {
                $tokens[] = ['type' => $c === '(' ? 'lparen' : ($c === ')' ? 'rparen' : ($c === ',' ? 'comma' : 'op')), 'value' => $c];
                $i++;

                continue;
            }

            // string literal ' or "
            if ($c === '"' || $c === "'") {
                [$value, $i] = $this->readString($s, $i, $c);
                $tokens[] = ['type' => 'str', 'value' => $value];

                continue;
            }

            // number
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                $start = $i;
                while ($i < $len && (ctype_digit($s[$i]) || $s[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['type' => 'num', 'value' => substr($s, $start, $i - $start)];

                continue;
            }

            // identifier
            if (ctype_alpha($c) || $c === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($s[$i]) || $s[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['type' => 'ident', 'value' => substr($s, $start, $i - $start)];

                continue;
            }

            throw new RuntimeException("Unexpected character '{$c}'.");
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readString(string $s, int $i, string $quote): array
    {
        $len = strlen($s);
        $i++; // skip opening quote
        $out = '';
        while ($i < $len) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $out .= $s[$i + 1];
                $i += 2;

                continue;
            }
            if ($c === $quote) {
                return [$out, $i + 1];
            }
            $out .= $c;
            $i++;
        }
        throw new RuntimeException('Unterminated string literal.');
    }

    /** @return array{type: string, value: string}|null */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function isOp(string $value): bool
    {
        $t = $this->peek();

        return $t !== null && $t['type'] === 'op' && $t['value'] === $value;
    }

    /** @return array<int, mixed> */
    private function parseExpression(): array
    {
        return $this->parseTernary();
    }

    /** @return array<int, mixed> */
    private function parseTernary(): array
    {
        $cond = $this->parseBinary(0);
        if ($this->isOp('?')) {
            $this->pos++;
            $then = $this->parseExpression();
            if (! $this->isOp(':')) {
                throw new RuntimeException("Expected ':' in ternary.");
            }
            $this->pos++;
            $else = $this->parseExpression();

            return ['ternary', $cond, $then, $else];
        }

        return $cond;
    }

    /**
     * Precedence-climbing over the binary operator levels.
     *
     * @return array<int, mixed>
     */
    private function parseBinary(int $level): array
    {
        $levels = [
            ['||'],
            ['&&'],
            ['==', '!='],
            ['<', '>', '<=', '>='],
            ['+', '-', '~'],
            ['*', '/', '%'],
        ];

        if ($level >= count($levels)) {
            return $this->parseUnary();
        }

        $left = $this->parseBinary($level + 1);

        while (($t = $this->peek()) !== null && $t['type'] === 'op' && in_array($t['value'], $levels[$level], true)) {
            $op = $t['value'];
            $this->pos++;
            $right = $this->parseBinary($level + 1);
            $left = ['bin', $op, $left, $right];
        }

        return $left;
    }

    /** @return array<int, mixed> */
    private function parseUnary(): array
    {
        if ($this->isOp('!') || $this->isOp('-')) {
            $op = $this->tokens[$this->pos]['value'];
            $this->pos++;

            return ['unary', $op, $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    /** @return array<int, mixed> */
    private function parsePrimary(): array
    {
        $t = $this->peek();
        if ($t === null) {
            throw new RuntimeException('Unexpected end of expression.');
        }

        if ($t['type'] === 'num') {
            $this->pos++;

            return ['lit', str_contains($t['value'], '.') ? (float) $t['value'] : (int) $t['value']];
        }

        if ($t['type'] === 'str') {
            $this->pos++;

            return ['lit', $t['value']];
        }

        if ($t['type'] === 'lparen') {
            $this->pos++;
            $expr = $this->parseExpression();
            if ($this->peek() === null || $this->peek()['type'] !== 'rparen') {
                throw new RuntimeException("Expected ')'.");
            }
            $this->pos++;

            return $expr;
        }

        if ($t['type'] === 'ident') {
            $this->pos++;
            $name = $t['value'];

            if (in_array($name, ['true', 'false', 'null'], true)) {
                return ['lit', match ($name) {
                    'true' => true, 'false' => false, default => null
                }];
            }

            // function call
            if ($this->peek() !== null && $this->peek()['type'] === 'lparen') {
                $this->pos++;
                $args = $this->parseArgs();

                return ['call', $name, $args];
            }

            return ['var', $name];
        }

        throw new RuntimeException("Unexpected token '{$t['value']}'.");
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function parseArgs(): array
    {
        $args = [];
        if ($this->peek() !== null && $this->peek()['type'] === 'rparen') {
            $this->pos++;

            return $args;
        }

        while (true) {
            $args[] = $this->parseExpression();
            $t = $this->peek();
            if ($t !== null && $t['type'] === 'comma') {
                $this->pos++;

                continue;
            }
            if ($t !== null && $t['type'] === 'rparen') {
                $this->pos++;
                break;
            }
            throw new RuntimeException("Expected ',' or ')' in argument list.");
        }

        return $args;
    }

    // --- evaluation ------------------------------------------------------------------------------

    /**
     * @param  array<int, mixed>  $node  positional AST node: [kind, ...operands] (see parse* builders)
     * @param  array<string, mixed>  $vars
     */
    private function run(array $node, array $vars): mixed
    {
        return match (is_string($node[0] ?? null) ? $node[0] : '') {
            'lit' => $node[1] ?? null,
            'var' => $vars[$this->opAt($node, 1)] ?? null,
            'unary' => $this->unary($this->opAt($node, 1), $this->run($this->childAt($node, 2), $vars)),
            'bin' => $this->binary($this->opAt($node, 1), $this->run($this->childAt($node, 2), $vars), $this->run($this->childAt($node, 3), $vars)),
            'ternary' => $this->truthy($this->run($this->childAt($node, 1), $vars)) ? $this->run($this->childAt($node, 2), $vars) : $this->run($this->childAt($node, 3), $vars),
            'call' => $this->call($this->opAt($node, 1), $this->evalArgs($node, $vars)),
            default => throw new RuntimeException('Unknown node.'),
        };
    }

    /**
     * A child AST node at a positional offset (operator/function operands).
     *
     * @param  array<int, mixed>  $node
     * @return array<int, mixed>
     */
    private function childAt(array $node, int $i): array
    {
        $child = $node[$i] ?? null;
        if (! is_array($child)) {
            throw new RuntimeException('Malformed AST node.');
        }

        /** @var array<int, mixed> $child */
        return $child;
    }

    /**
     * A string slot at a positional offset (operator symbol / variable name / function name).
     *
     * @param  array<int, mixed>  $node
     */
    private function opAt(array $node, int $i): string
    {
        return is_string($node[$i] ?? null) ? $node[$i] : '';
    }

    /**
     * Evaluate a call node's argument list (positioned at offset 2).
     *
     * @param  array<int, mixed>  $node
     * @param  array<string, mixed>  $vars
     * @return list<mixed>
     */
    private function evalArgs(array $node, array $vars): array
    {
        $args = $node[2] ?? null;
        if (! is_array($args)) {
            return [];
        }

        $out = [];
        foreach ($args as $arg) {
            $out[] = is_array($arg) ? $this->run($arg, $vars) : null;
        }

        return $out;
    }

    private function unary(string $op, mixed $v): mixed
    {
        return match ($op) {
            '!' => ! $this->truthy($v),
            '-' => -$this->num($v),
            default => throw new RuntimeException("Unknown unary '{$op}'."),
        };
    }

    private function binary(string $op, mixed $l, mixed $r): mixed
    {
        return match ($op) {
            '~' => $this->stringify($l).$this->stringify($r),
            '+' => $this->num($l) + $this->num($r),
            '-' => $this->num($l) - $this->num($r),
            '*' => $this->num($l) * $this->num($r),
            '/' => $this->num($r) === 0.0 ? null : $this->num($l) / $this->num($r),
            '%' => (int) $this->num($r) === 0 ? null : $this->num($l) % $this->num($r),
            '==' => $this->looseEquals($l, $r),
            '!=' => ! $this->looseEquals($l, $r),
            '<' => $this->compare($l, $r) < 0,
            '>' => $this->compare($l, $r) > 0,
            '<=' => $this->compare($l, $r) <= 0,
            '>=' => $this->compare($l, $r) >= 0,
            '&&' => $this->truthy($l) && $this->truthy($r),
            '||' => $this->truthy($l) || $this->truthy($r),
            default => throw new RuntimeException("Unknown operator '{$op}'."),
        };
    }

    /**
     * @param  list<mixed>  $args
     */
    private function call(string $name, array $args): mixed
    {
        return match ($name) {
            'concat' => implode('', array_map(fn (mixed $a): string => $this->stringify($a), $args)),
            'coalesce' => $this->coalesce($args),
            'upper' => strtoupper($this->stringify($args[0] ?? null)),
            'lower' => strtolower($this->stringify($args[0] ?? null)),
            'length' => mb_strlen($this->stringify($args[0] ?? null)),
            'round' => round($this->num($args[0] ?? null), (int) $this->num($args[1] ?? 0)),
            'abs' => abs($this->num($args[0] ?? null)),
            'min' => $args === [] ? null : min(array_map(fn (mixed $a): float => $this->num($a), $args)),
            'max' => $args === [] ? null : max(array_map(fn (mixed $a): float => $this->num($a), $args)),
            default => throw new RuntimeException("Unknown function '{$name}'."),
        };
    }

    /** @param list<mixed> $args */
    private function coalesce(array $args): mixed
    {
        foreach ($args as $a) {
            if ($a !== null && $a !== '') {
                return $a;
            }
        }

        return null;
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function stringify(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            is_bool($v) => $v ? 'true' : 'false',
            is_scalar($v) => (string) $v,
            default => '',
        };
    }

    private function truthy(mixed $v): bool
    {
        if (is_string($v)) {
            return $v !== '' && $v !== '0';
        }

        return (bool) $v;
    }

    private function looseEquals(mixed $l, mixed $r): bool
    {
        if (is_numeric($l) && is_numeric($r)) {
            return (float) $l === (float) $r;
        }

        return $this->stringify($l) === $this->stringify($r);
    }

    private function compare(mixed $l, mixed $r): int
    {
        if (is_numeric($l) && is_numeric($r)) {
            return (float) $l <=> (float) $r;
        }

        return $this->stringify($l) <=> $this->stringify($r);
    }
}
