<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('occurred_at', 40);
            $table->string('source', 16);
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('moving_seconds');
            $table->unsignedInteger('elapsed_seconds');
            $table->integer('elevation_gain_meters');
            $table->double('average_pace_seconds_per_km');
            $table->json('splits');
            $table->json('best_efforts');
            $table->string('external_id', 128)->nullable();
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            // Dedupes imported activities per (tenant, source, external_id). Manual
            // activities have a NULL external_id and are intentionally NOT deduped
            // (NULLs are distinct in a unique index).
            $table->unique(['tenant_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
