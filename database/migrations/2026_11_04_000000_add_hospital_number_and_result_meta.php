<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'hospitalNumber')) {
                $table->string('hospitalNumber', 50)->nullable()->index()->after('nin');
            }
        });

        // Optional photo / PDF of the lab machine printout, attached to a result.
        if (Schema::hasTable('screening_samples')) {
            Schema::table('screening_samples', function (Blueprint $table) {
                if (!Schema::hasColumn('screening_samples', 'resultAttachmentPath')) {
                    $table->string('resultAttachmentPath')->nullable();
                }
                if (!Schema::hasColumn('screening_samples', 'resultAttachmentName')) {
                    $table->string('resultAttachmentName', 200)->nullable();
                }
                if (!Schema::hasColumn('screening_samples', 'resultAttachmentMime')) {
                    $table->string('resultAttachmentMime', 100)->nullable();
                }
            });
        }

        // Extra lab-run details (assay, run status, lot, control Ct...) stored with a result.
        if (Schema::hasTable('screening_samples') && !Schema::hasColumn('screening_samples', 'resultMeta')) {
            Schema::table('screening_samples', function (Blueprint $table) {
                $table->json('resultMeta')->nullable()->after('resultDetail');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'hospitalNumber')) {
                $table->dropColumn('hospitalNumber');
            }
        });
        if (Schema::hasTable('screening_samples')) {
            foreach (['resultMeta', 'resultAttachmentPath', 'resultAttachmentName', 'resultAttachmentMime'] as $col) {
                if (Schema::hasColumn('screening_samples', $col)) {
                    Schema::table('screening_samples', fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }
    }
};
