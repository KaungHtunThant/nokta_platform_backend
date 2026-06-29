<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable history of a layout's schema (ARCHITECTURE §5). Every builder save writes a new version
 * row; publish points layouts.version at one of them; rollback restores a prior version's schema.
 * The current schema lives on `layouts`; this table is the append-only trail enabling rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('layout_id')->constrained()->cascadeOnDelete();
            $table->integer('version');
            $table->integer('schema_version')->default(1);
            $table->json('schema');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['layout_id', 'version']);
            $table->index(['tenant_id', 'layout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_versions');
    }
};
