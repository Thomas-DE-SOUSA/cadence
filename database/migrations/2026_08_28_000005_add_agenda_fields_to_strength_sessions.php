<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strength_sessions', function (Blueprint $table): void {
            $table->string('status', 16)->default('DONE');   // existing rows are logged workouts → done
            $table->uuid('template_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('strength_sessions', function (Blueprint $table): void {
            $table->dropColumn(['status', 'template_id']);
        });
    }
};
