<?php

declare(strict_types=1);

namespace App\Services\Records;

use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Models\RoleFieldAccess;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Field-level authorization (ARCHITECTURE §6) — the OPERATION-side enforcement of role_field_access.
 * Strips non-updatable fields on write and non-readable fields on read, so a forced client payload
 * cannot touch a field the role may not write, and a hidden PHI field is never returned.
 *
 * Semantics: explicit-deny-wins. A field is open unless one of the user's (tenant-scoped) roles has a
 * role_field_access row that denies it. Absence of any row = open (matrices only restrict). This is
 * the conservative choice for field-level PHI control (Phase 7). `ui_visible` is NOT consulted here —
 * it hides in the UI only and is never trusted for security.
 */
final class FieldGate
{
    /**
     * Keys among $defs the user may READ.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @return list<string>
     */
    public function readableKeys(User $user, Collection $defs): array
    {
        $denied = $this->deniedRows($user, $defs);

        /** @var list<string> $keys */
        $keys = $defs
            ->reject(fn (FieldDefinition $d): bool => ($denied[$d->id] ?? collect())->contains(fn (RoleFieldAccess $r): bool => ! $r->can_read))
            ->pluck('key')
            ->values()
            ->all();

        return $keys;
    }

    /**
     * Keys of $type the user may READ (loads the entity type's field definitions).
     *
     * @return list<string>
     */
    public function readableKeysForType(User $user, EntityType $type): array
    {
        return $this->readableKeys($user, $type->fieldDefinitions()->get());
    }

    /**
     * Whether the user may UPDATE a single field (used by the file-upload path, which writes one
     * field at a time). Same explicit-deny-wins semantics as stripUnwritable().
     */
    public function canUpdate(User $user, FieldDefinition $def): bool
    {
        $denied = $this->deniedRows($user, collect([$def]));

        return ! ($denied[$def->id] ?? collect())->contains(fn (RoleFieldAccess $r): bool => ! $r->can_update);
    }

    /**
     * Drop any data keys the user may not UPDATE (forced payloads cannot reach denied fields).
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, FieldDefinition>  $defs
     * @return array<string, mixed>
     */
    public function stripUnwritable(array $data, User $user, Collection $defs): array
    {
        $denied = $this->deniedRows($user, $defs);

        $blockedKeys = $defs
            ->filter(fn (FieldDefinition $d): bool => ($denied[$d->id] ?? collect())->contains(fn (RoleFieldAccess $r): bool => ! $r->can_update))
            ->pluck('key')
            ->all();

        return array_diff_key($data, array_flip($blockedKeys));
    }

    /**
     * role_field_access rows for the user's tenant-scoped roles, grouped by field_definition_id.
     *
     * @param  Collection<int, FieldDefinition>  $defs
     * @return Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, RoleFieldAccess>>
     */
    private function deniedRows(User $user, Collection $defs): Collection
    {
        /** @var list<int> $roleIds */
        $roleIds = $user->roles->pluck('id')->all();
        $fieldIds = $defs->pluck('id')->all();

        if ($roleIds === [] || $fieldIds === []) {
            return collect();
        }

        return RoleFieldAccess::query()
            ->whereIn('role_id', $roleIds)
            ->whereIn('field_definition_id', $fieldIds)
            ->get()
            ->groupBy('field_definition_id');
    }
}
