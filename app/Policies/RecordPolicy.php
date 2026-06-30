<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Models\Record;
use App\Models\User;

/**
 * Object-level authorization for records — the OPERATION-side source of truth (decision #5).
 * Every check resolves against the "op" guard EXPLICITLY (the user belongs to multiple guards,
 * so the default-guard derivation cannot be trusted here). Tenant scoping is implicit: roles are
 * team-scoped to the active tenant (set by ResolveTenant), and records are globally tenant-scoped.
 */
final class RecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, OperationPermission::RecordRead);
    }

    public function view(User $user, Record $record): bool
    {
        return $this->can($user, OperationPermission::RecordRead);
    }

    public function create(User $user): bool
    {
        return $this->can($user, OperationPermission::RecordCreate);
    }

    public function update(User $user, Record $record): bool
    {
        // A locked (signed) record is append-only: no update even with record.update (Phase 7).
        return ! $record->is_locked && $this->can($user, OperationPermission::RecordUpdate);
    }

    public function delete(User $user, Record $record): bool
    {
        return ! $record->is_locked && $this->can($user, OperationPermission::RecordDelete);
    }

    public function move(User $user, Record $record): bool
    {
        return ! $record->is_locked && $this->can($user, OperationPermission::StageMove);
    }

    public function lock(User $user, Record $record): bool
    {
        return $this->can($user, OperationPermission::RecordLock);
    }

    public function unlock(User $user, Record $record): bool
    {
        return $this->can($user, OperationPermission::RecordUnlock);
    }

    private function can(User $user, OperationPermission $permission): bool
    {
        return $user->hasPermissionTo($permission->value, OperationPermission::GUARD);
    }
}
