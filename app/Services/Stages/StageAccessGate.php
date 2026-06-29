<?php

declare(strict_types=1);

namespace App\Services\Stages;

use App\Models\RoleStageAccess;
use App\Models\Stage;
use App\Models\User;

/**
 * Per-stage move authorization (ARCHITECTURE §6, generalizes role_has_stages). Object-level grants
 * complement the coarse `stage.move` operation permission: a role may be allowed to move records
 * INTO some stages and OUT of others.
 *
 * Default-open / opt-in-to-strict: a stage is UNGOVERNED until at least one role_stage_access row
 * references it; ungoverned stages allow any move (preserves Phase 2 behaviour). Once a stage is
 * governed, a move out of / into it requires one of the actor's tenant-scoped roles to grant
 * can_move_from / can_move_to respectively.
 */
final class StageAccessGate
{
    /**
     * @return list<string> rejection reasons; empty means allowed.
     */
    public function check(User $actor, ?Stage $from, Stage $to): array
    {
        /** @var list<int> $roleIds */
        $roleIds = $actor->roles->pluck('id')->all();
        $reasons = [];

        if ($from !== null && $this->isGoverned($from) && ! $this->grants($roleIds, $from, 'can_move_from')) {
            $reasons[] = "You are not allowed to move records out of the '{$from->key}' stage.";
        }

        if ($this->isGoverned($to) && ! $this->grants($roleIds, $to, 'can_move_to')) {
            $reasons[] = "You are not allowed to move records into the '{$to->key}' stage.";
        }

        return $reasons;
    }

    private function isGoverned(Stage $stage): bool
    {
        return RoleStageAccess::query()->where('stage_id', $stage->id)->exists();
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function grants(array $roleIds, Stage $stage, string $column): bool
    {
        if ($roleIds === []) {
            return false;
        }

        return RoleStageAccess::query()
            ->whereIn('role_id', $roleIds)
            ->where('stage_id', $stage->id)
            ->where($column, true)
            ->exists();
    }
}
