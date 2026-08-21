<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->uuid('program_id');
            $table->uuid('cycle_id');
            $table->string('session_date', 40);
            $table->json('messages');
            $table->unsignedInteger('version');
            $table->timestamps();

            $table->index(['program_id', 'session_date', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
