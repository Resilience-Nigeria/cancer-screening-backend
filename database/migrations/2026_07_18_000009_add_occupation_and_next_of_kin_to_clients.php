<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'occupation')) {
                $table->string('occupation')->nullable()->after('address');
            }
            if (!Schema::hasColumn('clients', 'nextOfKinName')) {
                $table->string('nextOfKinName')->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('clients', 'nextOfKinPhone')) {
                $table->string('nextOfKinPhone')->nullable()->after('nextOfKinName');
            }
            if (!Schema::hasColumn('clients', 'nextOfKinRelationship')) {
                $table->string('nextOfKinRelationship')->nullable()->after('nextOfKinPhone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'nextOfKinName', 'nextOfKinPhone', 'nextOfKinRelationship']);
        });
    }
};
