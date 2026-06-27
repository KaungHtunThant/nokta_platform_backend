<?php

declare(strict_types=1);
use Tests\TestCase;

/*
 | Pest bootstrap. Feature/Unit tests extend Laravel's TestCase.
 | Architecture tests (tests/Arch) enforce the MVC + SOLID layering — see ArchTest.php.
 */

uses(TestCase::class)->in('Feature', 'Unit');
