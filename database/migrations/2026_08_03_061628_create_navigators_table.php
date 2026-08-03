<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('navigators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('facilityId')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamps();


            $table->foreign('userId')->references('id')->on('users')->nullOnDelete();
            $table->foreign('facilityId')->references('facilityId')->on('facilities')->nullOnDelete();
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->unsignedBigInteger('lastAssignedNavigatorId')->nullable()->after('navigatorPhone');
            $table->foreign('lastAssignedNavigatorId')->references('id')->on('navigators')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigators');
    }
};
