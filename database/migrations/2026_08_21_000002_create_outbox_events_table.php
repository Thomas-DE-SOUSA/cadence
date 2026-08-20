<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('aggregate_id');
            $table->string('aggregate_type', 64);
            $table->string('tenant_id', 64);
            $table->string('event_name', 128);
            $table->json('payload');
            $table->string('user_id', 64)->nullable();
            $table->unsignedInteger('version');
            $table->string('occurred_at', 40);
            $table->boolean('published')->default(false);
            $table->string('published_at', 40)->nullable();

            $table->unique(['aggregate_id', 'version']);
            $table->index(['published', 'occurred_at']);
            $table->index('aggregate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
