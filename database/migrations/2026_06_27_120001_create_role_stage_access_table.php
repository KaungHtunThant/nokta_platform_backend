<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-stage capability matrix (ARCHITECTURE §6, generalizes the old role_has_stages).
 * Object-level grants avoid permission-name explosion: one row per (role, stage) says
 * whether that role may move records OUT of, INTO, or VIEW that stage. Tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_stage_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_move_from')->default(false);
            $table->boolean('can_move_to')->default(false);
            $table->boolean('can_view')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'role_id', 'stage_id']);
            $table->index(['tenant_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_stage_access');
    }
};
