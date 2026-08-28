<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('exercises');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_templates');
    }
};
