<?php

declare(strict_types=1);

namespace App\Services\Stages;

use App\Contracts\StageRuleEvaluator;
use App\DTOs\StageMoveContext;
use App\Models\StageRule;
use Illuminate\Validation\ValidationException;

/**
 * Rule-driven transition policy (generalizes DealPolicy). Loads the target stage's rules and
 * delegates each to its evaluator; collects rejection reasons. The set of evaluators is injected
 * (tagged), so adding a rule type never touches this class (OCP).
 */
final class StageTransitionPolicy
{
    /** @var array<string, StageRuleEvaluator> */
    private array $evaluators = [];

    public function __construct(
        private readonly StageAccessGate $stageAccess,
        StageRuleEvaluator ...$evaluators,
    ) {
        foreach ($evaluators as $evaluator) {
            $this->evaluators[$evaluator->key()] = $evaluator;
        }
    }

    /**
     * @return list<string> rejection reasons; empty means the move is allowed.
     */
    public function check(StageMoveContext $context): array
    {
        $reasons = [];

        // Per-stage access (role_stage_access): can this actor move out of / into these stages?
        if ($context->actor !== null) {
            $reasons = array_merge(
                $reasons,
                $this->stageAccess->check($context->actor, $context->fromStage, $context->toStage),
            );
        }

        foreach ($context->toStage->rules as $rule) {
            /** @var StageRule $rule */
            $evaluator = $this->evaluators[$rule->rule] ?? null;
            if ($evaluator === null) {
                continue; // tolerate unknown rule keys
            }

            $reason = $evaluator->evaluate($context, $rule->value);
            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @throws ValidationException when any rule rejects the transition.
     */
    public function assert(StageMoveContext $context): void
    {
        $reasons = $this->check($context);

        if ($reasons !== []) {
            throw ValidationException::withMessages(['stage' => $reasons]);
        }
    }
}
