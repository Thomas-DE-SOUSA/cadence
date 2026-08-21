<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('program_id');
            $table->string('tenant_id', 64);
            $table->string('name', 160);
            $table->string('focus', 500);
            $table->string('start_date', 40);
            $table->string('end_date', 40);
            $table->unsignedInteger('phase_index')->default(0);
            $table->string('status', 16)->default('active');
            $table->json('sessions');
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
