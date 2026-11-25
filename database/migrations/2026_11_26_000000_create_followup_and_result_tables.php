<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-ups & recalls, results given back to clients, and SMS sent to clients.
 *  result_communications  how and when a released result was given back (and counselling)
 *  follow_up_contacts     every call / visit / SMS attempt to bring a client back, with outcome and next contact date
 *  sms_logs               every SMS sent to a client (who, why, provider answer)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('result_communications')) {
            Schema::create('result_communications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sampleId')->index();
                $t->string('clientId')->index();
                $t->string('method', 20);                 // in_person | phone | sms | whatsapp | other
                $t->dateTime('communicatedAt');
                $t->boolean('counselling')->default(false);
                $t->string('counselledBy', 120)->nullable();
                $t->boolean('clientUnderstood')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('userId')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('follow_up_contacts')) {
            Schema::create('follow_up_contacts', function (Blueprint $t) {
                $t->id();
                $t->string('clientId')->index();
                $t->string('kind', 20)->index();          // recall | result | booked | referral
                $t->string('refId', 60)->nullable()->index();
                $t->string('method', 20)->nullable();     // call | sms | visit | whatsapp
                $t->string('outcome', 30);                // reached | no_answer | wrong_number | rescheduled | declined | returned
                $t->text('note')->nullable();
                $t->date('nextContactDate')->nullable();
                $t->unsignedBigInteger('userId')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $t) {
                $t->id();
                $t->string('clientId')->nullable()->index();
                $t->string('phone', 30);
                $t->string('purpose', 30);                // result_ready | recall | booked_test | referral | custom
                $t->text('message');
                $t->string('driver', 30);
                $t->string('status', 20);                 // sent | failed | logged
                $t->text('response')->nullable();
                $t->unsignedBigInteger('userId')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('follow_up_contacts');
        Schema::dropIfExists('result_communications');
    }
};
