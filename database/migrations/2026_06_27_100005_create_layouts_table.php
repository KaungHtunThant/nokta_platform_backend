<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Versioned UI configuration per surface (nav|header|board|card|detail|form).
// layout_versions + builder land in Phase 5; Phase 1 serves form/detail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('surface');             // nav|header|board|card|detail|form
            $table->string('key');
            $table->string('scope')->default('tenant');
            $table->json('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->integer('schema_version')->default(1);
            $table->json('schema');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'surface', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layouts');
    }
};
