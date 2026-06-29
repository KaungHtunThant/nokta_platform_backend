<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-field capability matrix (ARCHITECTURE §6). One row per (role, field): can_read / can_update
 * are the OPERATION-side source of truth enforced by FieldGate; ui_visible only hides in the UI and
 * is NEVER trusted for security. Tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_field_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_read')->default(true);
            $table->boolean('can_update')->default(true);
            $table->boolean('ui_visible')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'role_id', 'field_definition_id']);
            $table->index(['tenant_id', 'field_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_field_access');
    }
};
