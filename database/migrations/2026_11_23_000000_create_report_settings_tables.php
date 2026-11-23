<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who receives the scheduled emails, and when.
 *   report_settings   — one row per report (weekly_summary, overdue_results): on/off, day, time, options
 *   report_recipients — who gets it: a whole role, one staff member (or an exclusion), or any email address,
 *                       each with the facilities it covers (own / all / chosen facilities)
 *
 * Seeded so nothing changes on day one:
 *   overdue_results: every role, "own facilities" (the current behaviour), daily 09:30
 *   weekly_summary : NICRAT Super Admin + NICRAT Admin, all facilities, Fridays 17:00
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_settings', function (Blueprint $table) {
            $table->string('reportKey', 50)->primary();
            $table->boolean('enabled')->default(true);
            $table->string('frequency', 20)->default('daily');     // daily | weekdays | weekly
            $table->unsignedTinyInteger('dayOfWeek')->nullable();  // 0 = Sunday … 6 = Saturday (weekly only)
            $table->string('sendTime', 5)->default('09:00');       // HH:MM, Africa/Lagos
            $table->json('options')->nullable();
            $table->timestamp('lastRunAt')->nullable();
            $table->unsignedBigInteger('updatedBy')->nullable();
            $table->timestamps();
        });

        Schema::create('report_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('reportKey', 50)->index();
            $table->string('type', 10);                            // role | user | email
            $table->string('roleName', 100)->nullable();
            $table->unsignedBigInteger('userId')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('scope', 20)->default('own');           // own | all | facilities
            $table->json('facilityIds')->nullable();
            $table->boolean('isExcluded')->default(false);         // user rows only: never send to this person
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('report_settings')->insert([
            [
                'reportKey' => 'overdue_results', 'enabled' => true, 'frequency' => 'daily', 'dayOfWeek' => null,
                'sendTime' => '09:30', 'options' => json_encode(['days' => 7, 'applyToPopup' => false]),
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'reportKey' => 'weekly_summary', 'enabled' => true, 'frequency' => 'weekly', 'dayOfWeek' => 5,
                'sendTime' => '17:00', 'options' => json_encode([]),
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $roles = Schema::hasTable('roles') ? DB::table('roles')->pluck('roleName')->filter()->unique()->values() : collect();
        $rows = [];
        foreach ($roles as $role) {
            $rows[] = ['reportKey' => 'overdue_results', 'type' => 'role', 'roleName' => $role, 'scope' => 'own',
                       'created_at' => $now, 'updated_at' => $now];
        }
        foreach (['NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN'] as $role) {
            if ($roles->isEmpty() || $roles->contains($role)) {
                $rows[] = ['reportKey' => 'weekly_summary', 'type' => 'role', 'roleName' => $role, 'scope' => 'all',
                           'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows) DB::table('report_recipients')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_recipients');
        Schema::dropIfExists('report_settings');
    }
};
