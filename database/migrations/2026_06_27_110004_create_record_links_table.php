<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('from_record_id');
            $table->unsignedBigInteger('to_record_id');
            $table->string('relation_key'); // e.g. 'deal.contact', 'patient.deals'
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'from_record_id', 'relation_key']);
            $table->index(['tenant_id', 'to_record_id', 'relation_key']);
            $table->unique(['from_record_id', 'to_record_id', 'relation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_links');
    }
};
