<?php

declare(strict_types=1);

namespace App\Enums\RolesAndPermissions;

/**
 * OPERATION permissions — what a user may DO. Backend source of truth (guard: "op").
 * Enum-backed catalog: seeded once, PERMANENT (never created/deleted at runtime).
 * Roles are customizable and draw permissions from this fixed catalog.
 *
 * Fine-grained per-stage / per-field grants use capability matrices
 * (role_stage_access, role_field_access), not enum cases.
 */
enum OperationPermission: string
{
    case RecordCreate = 'record.create';
    case RecordRead = 'record.read';
    case RecordUpdate = 'record.update';
    case RecordDelete = 'record.delete';
    case StageMove = 'stage.move';

    case ManageEntityTypes = 'manage.entity-types';
    case ManageFields = 'manage.fields';
    case ManageLayouts = 'manage.layouts';
    case ManageRoles = 'manage.roles';
    case ManageTranslations = 'manage.translations';

    case ImportData = 'data.import';
    case ExportData = 'data.export';
    case ViewAudit = 'audit.view';

    public const GUARD = 'op';
}
