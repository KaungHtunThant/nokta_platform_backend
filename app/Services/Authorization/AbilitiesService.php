<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use App\Models\RoleFieldAccess;
use App\Models\RoleStageAccess;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the effective abilities for a user in the ACTIVE tenant (ARCHITECTURE §6): the op + ui
 * permission sets (resolved via team-scoped roles) plus the per-stage and per-field capability
 * matrices. Mirrors the server-side enforcement so the frontend can gate rendering identically —
 * but every mutation is still re-checked server-side; this output is never the security boundary.
 *
 * Matrices contain only EXPLICITLY configured entries; absence means "open" (the renderer treats a
 * missing field/stage as fully accessible), matching FieldGate/StageAccessGate defaults.
 */
final class AbilitiesService
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $permissions = $user->getAllPermissions();
        /** @var list<int> $roleIds */
        $roleIds = $user->roles->pluck('id')->all();

        return [
            'op' => $permissions->where('guard_name', OperationPermission::GUARD)->pluck('name')->values()->all(),
            'ui' => $permissions->where('guard_name', UiPermission::GUARD)->pluck('name')->values()->all(),
            'stages' => $this->stageMatrix($roleIds),
            'fields' => $this->fieldMatrix($roleIds),
        ];
    }

    /**
     * @param  list<int>  $roleIds
     * @return array<int, array{can_move_from: bool, can_move_to: bool, can_view: bool}>
     */
    private function stageMatrix(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return RoleStageAccess::query()
            ->whereIn('role_id', $roleIds)
            ->get()
            ->groupBy('stage_id')
            ->map(fn (Collection $rows): array => [
                // most-permissive across the user's roles (matches StageAccessGate's OR grant)
                'can_move_from' => $rows->contains(fn (RoleStageAccess $r): bool => $r->can_move_from),
                'can_move_to' => $rows->contains(fn (RoleStageAccess $r): bool => $r->can_move_to),
                'can_view' => $rows->contains(fn (RoleStageAccess $r): bool => $r->can_view),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $roleIds
     * @return array<string, array{can_read: bool, can_update: bool, ui_visible: bool}>
     */
    private function fieldMatrix(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $out = [];

        RoleFieldAccess::query()
            ->whereIn('role_id', $roleIds)
            ->with('fieldDefinition:id,key')
            ->get()
            ->groupBy('field_definition_id')
            ->each(function (Collection $rows) use (&$out): void {
                $key = $rows->first()?->fieldDefinition?->key;
                if ($key === null) {
                    return;
                }
                // deny-wins across the user's roles (matches FieldGate)
                $out[$key] = [
                    'can_read' => $rows->every(fn (RoleFieldAccess $r): bool => $r->can_read),
                    'can_update' => $rows->every(fn (RoleFieldAccess $r): bool => $r->can_update),
                    'ui_visible' => $rows->every(fn (RoleFieldAccess $r): bool => $r->ui_visible),
                ];
            });

        return $out;
    }
}
