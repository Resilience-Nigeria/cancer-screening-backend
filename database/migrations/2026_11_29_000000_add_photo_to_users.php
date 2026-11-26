<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff profile pictures (uploaded from the NCSR Mobile App → My profile). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'photoPath')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('photoPath')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'photoPath')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('photoPath');
            });
        }
    }
};
