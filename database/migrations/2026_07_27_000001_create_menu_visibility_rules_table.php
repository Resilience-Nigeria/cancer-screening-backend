<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Which roles can see which nav menu item — configurable from
     * Settings instead of hardcoded in routes/sidebar.tsx. A menu item
     * with no row here, or an empty allowedRoles, is visible to every
     * authenticated user (matching the previous "no roles = everyone"
     * convention). This is a separate dimension from requiresStage
     * (facility capability) — both can apply to the same menu item.
     */
    public function up(): void
    {
        Schema::create('menu_visibility_rules', function (Blueprint $table) {
            $table->id('ruleId');
            $table->string('menuKey')->unique(); // the route path, e.g. /ncsr/analytics
            $table->string('menuLabel');
            $table->json('allowedRoles')->nullable(); // null/empty = everyone
            $table->timestamps();
        });

        // Seed with the current hardcoded restrictions from
        // routes/sidebar.tsx, so behavior doesn't change on migrate.
        // Menu items not listed here are left with allowedRoles = null
        // (visible to everyone), matching their current unrestricted state.
        $seeds = [
            ['/ncsr/analytics', 'Analytics', ['NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN', 'PARTNER']],
            ['/ncsr/users', 'User Management', ['NICRAT_SUPER_ADMIN', 'NAVIGATOR', 'HOSPITAL_ADMIN']],
            ['/ncsr/facilities', 'Facilities', ['NICRAT_SUPER_ADMIN']],
            ['/ncsr/roles', 'Role Data Scope', ['NICRAT_SUPER_ADMIN']],
            ['/ncsr/settings', 'Settings', ['NICRAT_SUPER_ADMIN']],
        ];

        foreach ($seeds as [$key, $label, $roles]) {
            DB::table('menu_visibility_rules')->insert([
                'menuKey' => $key,
                'menuLabel' => $label,
                'allowedRoles' => json_encode($roles),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Everything else — visible-to-all today — gets an explicit row
        // too (with allowedRoles = null), so the admin UI shows every
        // menu item in one place rather than only the restricted ones.
        $unrestricted = [
            ['/ncsr/dashboard', 'Dashboard'],
            ['/ncsr/clients', 'Clients'],
            ['/ncsr/all-visits', 'Visits'],
            ['/ncsr/screenings', 'Screenings'],
            ['/ncsr/outcomes', 'Outcomes'],
            ['/ncsr/referred', 'Linked Clients'],
            ['/ncsr/self-assessments', 'Stage 1: Self-Assessment Records'],
            ['/ncsr/clinical-screening', 'Stage 2: Clinical Screening'],
            ['/ncsr/diagnostic-evaluation', 'Stage 3: Diagnostic Evaluation'],
            ['/ncsr/treatment-plan', 'Stage 4: Treatment & Care'],
            ['/ncsr/treatments', 'Treatment Tracking'],
            ['/ncsr/follow-up-schedules', 'Follow-up Schedules'],
        ];

        foreach ($unrestricted as [$key, $label]) {
            DB::table('menu_visibility_rules')->insert([
                'menuKey' => $key,
                'menuLabel' => $label,
                'allowedRoles' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_visibility_rules');
    }
};
