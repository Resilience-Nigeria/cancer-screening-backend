<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock of screening kits and consumables per facility.
 *  stock_items      what is tracked (and which tests use it, how many per test)
 *  stock_levels     how many each facility has now, and its low-stock level
 *  stock_movements  every change: received, used by a sample, counted/adjusted, sent to another facility, expired
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_items')) {
            Schema::create('stock_items', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name', 120);
                $t->string('unit', 30)->default('pieces');
                $t->json('testTypes')->nullable();          // tests that use one each (auto-deduct)
                $t->decimal('perTest', 6, 2)->default(1);   // 0 = not deducted automatically
                $t->unsignedInteger('defaultLowLevel')->default(20);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
            $lab = ['hpv_dna', 'cervical_cytology', 'cervical_biopsy', 'breast_biopsy', 'psa', 'prostate_biopsy', 'afp', 'hbsag', 'anti_hcv', 'fit', 'colorectal_biopsy'];
            $items = [
                ['HPV_KIT', 'HPV DNA test kit', 'kits', ['hpv_dna'], 1, 20],
                ['SPECIMEN_CONTAINER', 'Specimen container / sample tube', 'pieces', $lab, 1, 50],
                ['PAP_KIT', 'Pap smear kit', 'kits', ['cervical_cytology'], 1, 20],
                ['ACETIC_ACID', 'Acetic acid 5% (VIA)', 'bottles', ['via'], 0, 3],
                ['LUGOL', "Lugol's iodine (VILI)", 'bottles', ['via'], 0, 3],
                ['PSA_KIT', 'PSA test kit', 'kits', ['psa'], 1, 20],
                ['AFP_KIT', 'AFP test kit', 'kits', ['afp'], 1, 10],
                ['HBSAG_KIT', 'HBsAg rapid test kit', 'kits', ['hbsag'], 1, 20],
                ['HCV_KIT', 'Anti-HCV rapid test kit', 'kits', ['anti_hcv'], 1, 20],
                ['FIT_KIT', 'FIT / FOBT kit', 'kits', ['fit'], 1, 20],
                ['BIOPSY_KIT', 'Biopsy pack (formalin pot)', 'packs', ['cervical_biopsy', 'breast_biopsy', 'prostate_biopsy', 'colorectal_biopsy'], 1, 10],
                ['SPECULUM', 'Disposable speculum', 'pieces', ['hpv_dna', 'cervical_cytology', 'via'], 1, 30],
                ['GLOVES', 'Examination gloves', 'pairs', [], 0, 50],
            ];
            foreach ($items as [$code, $name, $unit, $tests, $per, $low]) {
                DB::table('stock_items')->insert([
                    'code' => $code, 'name' => $name, 'unit' => $unit, 'testTypes' => json_encode($tests),
                    'perTest' => $per, 'defaultLowLevel' => $low, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        if (!Schema::hasTable('stock_levels')) {
            Schema::create('stock_levels', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('facilityId')->index();
                $t->unsignedBigInteger('itemId')->index();
                $t->decimal('quantity', 10, 2)->default(0);
                $t->unsignedInteger('lowLevel')->nullable();       // overrides the item's default
                $t->timestamp('lastAlertAt')->nullable();
                $t->timestamps();
                $t->unique(['facilityId', 'itemId']);
            });
        }
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('facilityId')->index();
                $t->unsignedBigInteger('itemId')->index();
                $t->string('type', 20);                           // receive | use | adjust | transfer_out | transfer_in | expired
                $t->decimal('quantity', 10, 2);                    // + in, − out
                $t->decimal('balanceAfter', 10, 2);
                $t->unsignedBigInteger('sampleId')->nullable()->index();
                $t->unsignedBigInteger('otherFacilityId')->nullable();
                $t->string('batchNo', 60)->nullable();
                $t->date('expiryDate')->nullable();
                $t->text('note')->nullable();
                $t->unsignedBigInteger('userId')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_items');
    }
};
