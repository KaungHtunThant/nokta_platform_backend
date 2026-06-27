<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable(); // sub-stages (later)
            $table->string('key');
            $table->json('label');
            $table->string('color')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pipeline_id', 'key']);
            $table->index(['tenant_id', 'pipeline_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
