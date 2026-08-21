<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 64);
            $table->json('profile');
            $table->string('created_at', 40);
            $table->unsignedInteger('version');
            $table->softDeletes();

            // One profile per tenant.
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_profiles');
    }
};
