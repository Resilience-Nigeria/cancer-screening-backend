<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id('logId');
            $table->string('action', 60)->index();          // e.g. client_id_changed, sample_deleted
            $table->string('subjectType', 40)->nullable();  // client | sample | deletion_request
            $table->string('subjectId', 100)->nullable()->index();
            $table->string('clientId')->nullable()->index();
            $table->unsignedBigInteger('facilityId')->nullable()->index();
            $table->text('description');
            $table->json('details')->nullable();            // before / after values, reason, etc.
            $table->unsignedBigInteger('userId')->nullable()->index();
            $table->string('userName')->nullable();
            $table->string('ipAddress', 45)->nullable();
            $table->timestamps();
        });

        // Bring in the Client ID changes made before this log existed.
        if (Schema::hasTable('client_id_changes')) {
            $rows = DB::table('client_id_changes')->orderBy('id')->get();
            foreach ($rows as $r) {
                $client = DB::table('clients')->where('clientId', $r->newClientId)->first();
                DB::table('activity_logs')->insert([
                    'action'      => 'client_id_changed',
                    'subjectType' => 'client',
                    'subjectId'   => $r->newClientId,
                    'clientId'    => $r->newClientId,
                    'facilityId'  => $client->facilityId ?? null,
                    'description' => "Client ID changed from {$r->oldClientId} to {$r->newClientId} (residence corrected).",
                    'details'     => json_encode([
                        'oldClientId' => $r->oldClientId,
                        'newClientId' => $r->newClientId,
                        'oldResidence' => trim(($r->oldLgaOfResidence ?? '') . ', ' . ($r->oldStateOfResidence ?? ''), ', '),
                        'newResidence' => trim(($r->newLgaOfResidence ?? '') . ', ' . ($r->newStateOfResidence ?? ''), ', '),
                        'reason' => $r->reason,
                    ]),
                    'userId'      => $r->changedById,
                    'created_at'  => $r->created_at,
                    'updated_at'  => $r->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
