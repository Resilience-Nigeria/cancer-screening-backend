<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breast screening done by health workers is saved twice:
 *  - in the NCSR registry (clients + screening_samples), so it shows on the Samples / Results pages;
 *  - in the Bloom registry (bloom_case_tests, a copy kept in step with the NCSR row), linked to the case.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bloom_cases', function (Blueprint $table) {
            $table->string('ncsr_client_id', 40)->nullable()->index()->after('account_id');
        });

        Schema::create('bloom_case_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->unsignedBigInteger('sample_id')->unique();      // screening_samples.sampleId
            $table->string('ncsr_client_id', 40)->index();
            $table->string('cancer_type', 30);
            $table->string('test_type', 50);
            $table->string('status', 20);                          // collected | result_released
            $table->string('sample_ref', 60)->nullable();
            $table->date('sample_date')->nullable();
            $table->string('specimen_type', 100)->nullable();
            $table->string('collection_method', 100)->nullable();
            $table->string('result', 50)->nullable();
            $table->string('result_severity', 20)->nullable();
            $table->string('result_detail', 100)->nullable();
            $table->decimal('result_value', 10, 2)->nullable();
            $table->text('result_meta')->nullable();               // JSON
            $table->date('result_date')->nullable();
            $table->text('result_notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable(); // users.id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_case_tests');
        Schema::table('bloom_cases', function (Blueprint $table) {
            $table->dropColumn('ncsr_client_id');
        });
    }
};
