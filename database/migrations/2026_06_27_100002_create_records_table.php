<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The generalized record table: a FEW locked typed columns + a JSON bag for custom fields.
// (EAV projection `record_values` arrives in Phase 4.)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_type_id')->constrained()->cascadeOnDelete();

            // Locked columns (indexed; pipeline mechanics land in Phase 2).
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->double('position')->default(0);
            $table->string('status')->nullable();
            $table->boolean('is_locked')->default(false);

            // Custom field values (json strategy).
            $table->json('data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'entity_type_id', 'stage_id']);
            $table->index(['tenant_id', 'entity_type_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
