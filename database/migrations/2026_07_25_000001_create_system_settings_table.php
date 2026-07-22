<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Simple key-value settings store. Lets things like the default
     * SMS/email provider be changed from the admin UI instead of being
     * hardcoded in service classes — adding a new provider later means
     * adding a case to the resolver, not rewiring every caller.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id('settingId');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, select
            $table->string('group')->default('general'); // general, notifications, security
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // for 'select' type — the allowed choices
            $table->timestamps();
        });

        $defaults = [
            // General
            ['key' => 'site_name', 'value' => 'National Cancer Screening Registry', 'type' => 'string', 'group' => 'general', 'label' => 'Platform Name'],
            ['key' => 'support_email', 'value' => 'support@resiliencenigeria.org', 'type' => 'string', 'group' => 'general', 'label' => 'Support Email'],
            ['key' => 'support_phone', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Support Phone'],
            ['key' => 'default_country_code', 'value' => '+234', 'type' => 'string', 'group' => 'general', 'label' => 'Default Country Code'],

            // Notifications — configurable providers, not hardcoded
            ['key' => 'sms_provider', 'value' => 'bulksms', 'type' => 'select', 'group' => 'notifications', 'label' => 'Default SMS Provider', 'options' => json_encode(['bulksms' => 'BulkSMS Nigeria'])],
            ['key' => 'email_provider', 'value' => 'brevo', 'type' => 'select', 'group' => 'notifications', 'label' => 'Default Email Provider', 'options' => json_encode(['brevo' => 'Brevo'])],
            ['key' => 'whatsapp_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Enable WhatsApp Notifications'],
            ['key' => 'follow_up_reminder_days_before', 'value' => '7', 'type' => 'integer', 'group' => 'notifications', 'label' => 'Send Follow-up Reminders (days before due date)'],
            ['key' => 'follow_up_missed_after_days', 'value' => '14', 'type' => 'integer', 'group' => 'notifications', 'label' => 'Mark Follow-up Missed After (days overdue)'],

            // Security
            ['key' => 'otp_expiry_minutes', 'value' => '10', 'type' => 'integer', 'group' => 'security', 'label' => 'OTP Expiry (minutes)'],
            ['key' => 'session_timeout_minutes', 'value' => '60', 'type' => 'integer', 'group' => 'security', 'label' => 'Session Timeout (minutes)'],
        ];

        foreach ($defaults as $row) {
            DB::table('system_settings')->insert([
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => $row['type'],
                'group' => $row['group'],
                'label' => $row['label'],
                'options' => $row['options'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
