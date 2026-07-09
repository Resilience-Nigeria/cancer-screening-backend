<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_add_type_and_navigator_to_facilities_table.php
public function up(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->json('facilityType')->nullable()->after('facilityName');
        // facilityType is a JSON array so one facility can be e.g. ["sub_hub","treatment_center"]

        $table->string('navigatorName')->nullable()->after('facilityType');
        $table->string('navigatorPhone')->nullable()->after('navigatorName');
        $table->string('navigatorEmail')->nullable()->after('navigatorPhone');
        $table->string('whatsappNumber')->nullable()->after('navigatorEmail');
    });
}

public function down(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->dropColumn([
            'facilityType',
            'navigatorName',
            'navigatorPhone',
            'navigatorEmail',
            'whatsappNumber',
        ]);
    });
}
};
