<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treatment_combos')) {
            Schema::create('treatment_combos', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedTinyInteger('region_count')->default(3);
                $table->decimal('extra_discount_percent', 5, 2)->default(15);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            return;
        }

        Schema::table('treatment_combos', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_combos', 'region_count')) {
                $table->unsignedTinyInteger('region_count')->default(3)->after('name');
            }
            if (! Schema::hasColumn('treatment_combos', 'extra_discount_percent')) {
                $table->decimal('extra_discount_percent', 5, 2)->default(15)->after('region_count');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('treatment_combos')) {
            Schema::table('treatment_combos', function (Blueprint $table) {
                if (Schema::hasColumn('treatment_combos', 'region_count')) {
                    $table->dropColumn('region_count');
                }
                if (Schema::hasColumn('treatment_combos', 'extra_discount_percent')) {
                    $table->dropColumn('extra_discount_percent');
                }
            });
        }
    }
};
