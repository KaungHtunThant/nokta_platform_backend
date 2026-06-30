<?php

declare(strict_types=1);

namespace App\Enums\RolesAndPermissions;

/**
 * UI permissions — what a user may SEE. Drives rendering only (guard: "ui").
 * NEVER trusted for security: hiding an element does not protect it; the matching
 * OPERATION permission does. Enum-backed catalog: seeded once, permanent.
 */
enum UiPermission: string
{
    case NavBoards = 'ui.nav.boards';
    case NavList = 'ui.nav.list';
    case NavContacts = 'ui.nav.contacts';
    case NavPatients = 'ui.nav.patients';
    case NavUsers = 'ui.nav.users';
    case NavSettings = 'ui.nav.settings';

    case ViewBoard = 'ui.board.view';
    case ViewSectionFinancials = 'ui.section.financials';
    case AccessBuilder = 'ui.access-builder';
    case AccessRolesSettings = 'ui.settings.roles';
    case AccessTranslationsSettings = 'ui.settings.translations';

    public const GUARD = 'ui';
}
