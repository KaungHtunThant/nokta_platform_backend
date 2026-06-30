<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: promote the canonical `records.contact_id` link to a real self-referential foreign key
 * (all entities live in `records`, so the canonical contact link points back into the same table).
 * Nullable + nullOnDelete: clearing/deleting the linked record detaches rather than cascades. The
 * column already exists (see create_records_table); this only adds the constraint + an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->index(['tenant_id', 'contact_id']);
            $table->foreign('contact_id')->references('id')->on('records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['tenant_id', 'contact_id']);
        });
    }
};
