<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->library() as [$name, $muscle, $equipment]) {
            DB::table('exercises')->updateOrInsert(
                ['name' => $name, 'tenant_id' => null],
                [
                    'id' => (string) Str::uuid(),
                    'primary_muscle' => $muscle,
                    'equipment' => $equipment,
                    'is_custom' => false,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('exercises')->whereNull('tenant_id')->delete();
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function library(): array
    {
        return [
            // Pectoraux
            ['Développé couché (barre)', 'CHEST', 'BARBELL'],
            ['Développé couché haltères', 'CHEST', 'DUMBBELL'],
            ['Développé incliné (barre)', 'CHEST', 'BARBELL'],
            ['Développé incliné haltères', 'CHEST', 'DUMBBELL'],
            ['Développé décliné', 'CHEST', 'BARBELL'],
            ['Chest press (machine)', 'CHEST', 'MACHINE'],
            ['Écarté poulie', 'CHEST', 'CABLE'],
            ['Écarté haltères', 'CHEST', 'DUMBBELL'],
            ['Pec deck', 'CHEST', 'MACHINE'],
            ['Pompes', 'CHEST', 'BODYWEIGHT'],
            ['Dips pectoraux', 'CHEST', 'BODYWEIGHT'],
            // Dos
            ['Tractions', 'BACK', 'BODYWEIGHT'],
            ['Tirage vertical (lat pulldown)', 'BACK', 'CABLE'],
            ['Rowing barre', 'BACK', 'BARBELL'],
            ['Rowing haltère (un bras)', 'BACK', 'DUMBBELL'],
            ['Rowing assis machine', 'BACK', 'MACHINE'],
            ['Tirage horizontal poulie', 'BACK', 'CABLE'],
            ['Soulevé de terre', 'BACK', 'BARBELL'],
            ['Pull-over', 'BACK', 'DUMBBELL'],
            ['Shrugs (trapèzes)', 'BACK', 'DUMBBELL'],
            ['T-bar row', 'BACK', 'BARBELL'],
            // Épaules
            ['Développé militaire (barre)', 'SHOULDERS', 'BARBELL'],
            ['Développé épaules haltères', 'SHOULDERS', 'DUMBBELL'],
            ['Développé épaules machine', 'SHOULDERS', 'MACHINE'],
            ['Élévations latérales', 'SHOULDERS', 'DUMBBELL'],
            ['Élévations latérales poulie', 'SHOULDERS', 'CABLE'],
            ['Oiseau (deltoïde postérieur)', 'SHOULDERS', 'DUMBBELL'],
            ['Face pull', 'SHOULDERS', 'CABLE'],
            ['Élévations frontales', 'SHOULDERS', 'DUMBBELL'],
            // Biceps
            ['Curl barre', 'BICEPS', 'BARBELL'],
            ['Curl haltères', 'BICEPS', 'DUMBBELL'],
            ['Curl marteau', 'BICEPS', 'DUMBBELL'],
            ['Curl pupitre', 'BICEPS', 'MACHINE'],
            ['Curl poulie', 'BICEPS', 'CABLE'],
            ['Curl incliné', 'BICEPS', 'DUMBBELL'],
            // Triceps
            ['Extension poulie (pushdown)', 'TRICEPS', 'CABLE'],
            ['Barre au front', 'TRICEPS', 'BARBELL'],
            ['Extension nuque haltère', 'TRICEPS', 'DUMBBELL'],
            ['Dips triceps', 'TRICEPS', 'BODYWEIGHT'],
            ['Extension poulie corde', 'TRICEPS', 'CABLE'],
            ['Kickback', 'TRICEPS', 'DUMBBELL'],
            // Quadriceps
            ['Squat (barre)', 'QUADS', 'BARBELL'],
            ['Front squat', 'QUADS', 'BARBELL'],
            ['Presse à cuisses', 'QUADS', 'MACHINE'],
            ['Leg extension', 'QUADS', 'MACHINE'],
            ['Fentes haltères', 'QUADS', 'DUMBBELL'],
            ['Hack squat', 'QUADS', 'MACHINE'],
            ['Squat gobelet', 'QUADS', 'KETTLEBELL'],
            ['Squat bulgare', 'QUADS', 'DUMBBELL'],
            // Ischios
            ['Leg curl allongé', 'HAMSTRINGS', 'MACHINE'],
            ['Leg curl assis', 'HAMSTRINGS', 'MACHINE'],
            ['Soulevé de terre roumain', 'HAMSTRINGS', 'BARBELL'],
            ['Good morning', 'HAMSTRINGS', 'BARBELL'],
            // Fessiers
            ['Hip thrust', 'GLUTES', 'BARBELL'],
            ['Kickback fessier poulie', 'GLUTES', 'CABLE'],
            ['Abduction machine', 'GLUTES', 'MACHINE'],
            // Mollets
            ['Mollets debout', 'CALVES', 'MACHINE'],
            ['Mollets assis', 'CALVES', 'MACHINE'],
            ['Mollets à la presse', 'CALVES', 'MACHINE'],
            // Gainage / abdos
            ['Gainage (planche)', 'CORE', 'BODYWEIGHT'],
            ['Relevé de jambes', 'CORE', 'BODYWEIGHT'],
            ['Crunch poulie', 'CORE', 'CABLE'],
            ['Roue abdominale', 'CORE', 'OTHER'],
            ['Russian twist', 'CORE', 'BODYWEIGHT'],
            ['Gainage latéral', 'CORE', 'BODYWEIGHT'],
            // Avant-bras
            ['Curl poignets', 'FOREARMS', 'BARBELL'],
            // Corps entier / haltéro
            ['Épaulé-jeté', 'FULL_BODY', 'BARBELL'],
            ['Arraché', 'FULL_BODY', 'BARBELL'],
            ['Kettlebell swing', 'FULL_BODY', 'KETTLEBELL'],
            ['Thruster', 'FULL_BODY', 'BARBELL'],
            ['Burpees', 'FULL_BODY', 'BODYWEIGHT'],
        ];
    }
};
