<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('body_region_id');
        });

        Schema::dropIfExists('body_regions');

        if (Schema::hasColumn('treatment_combos', 'region_count')) {
            Schema::table('treatment_combos', function (Blueprint $table) {
                $table->renameColumn('region_count', 'min_treatment_count');
            });
        }
    }

    public function down(): void
    {
        Schema::create('body_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->foreignId('body_region_id')->nullable()->constrained();
        });

        if (Schema::hasColumn('treatment_combos', 'min_treatment_count')) {
            Schema::table('treatment_combos', function (Blueprint $table) {
                $table->renameColumn('min_treatment_count', 'region_count');
            });
        }
    }
};
