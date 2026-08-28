<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strength_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('session_date', 10);
            $table->string('title')->default('');
            $table->string('note', 1000)->default('');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('exercises');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strength_sessions');
    }
};
