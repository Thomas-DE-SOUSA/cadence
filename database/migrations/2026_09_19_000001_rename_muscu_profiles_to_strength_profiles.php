<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// The strength-profile table was originally named `muscu_profiles`. It is now
// `strength_profiles` (all identifiers are English). Fresh installs already
// create the new name via the create migration, so this only has work to do on
// an existing deployment: carry the legacy table's data over, replacing the
// empty table the renamed create migration made on this same run.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muscu_profiles')) {
            return; // Fresh install: the create migration already made the right table.
        }

        Schema::dropIfExists('strength_profiles');
        Schema::rename('muscu_profiles', 'strength_profiles');
    }

    public function down(): void
    {
        if (Schema::hasTable('strength_profiles') && ! Schema::hasTable('muscu_profiles')) {
            Schema::rename('strength_profiles', 'muscu_profiles');
        }
    }
};
