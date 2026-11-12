<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'dobEstimated')) {
                $table->boolean('dobEstimated')->default(false)->after('dateOfBirth');
            }
            if (!Schema::hasColumn('clients', 'offlineId')) {
                $table->string('offlineId', 100)->nullable()->index()->after('clientId');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'offlineId')) {
                $table->dropColumn('offlineId');
            }
            if (Schema::hasColumn('clients', 'dobEstimated')) {
                $table->dropColumn('dobEstimated');
            }
        });
    }
};
