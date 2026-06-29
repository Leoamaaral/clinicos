<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            if (! Schema::hasColumn('treatments', 'package_6_price')) {
                $table->decimal('package_6_price', 10, 2)->nullable()->after('package_price');
            }
        });

        if (Schema::hasColumn('treatments', 'package_6_price')) {
            DB::table('treatments')
                ->whereNull('package_6_price')
                ->update([
                    'package_6_price' => DB::raw('ROUND(package_price * 6 / 10, 2)'),
                ]);
        }

        Schema::table('treatment_combos', function (Blueprint $table) {
            if (! Schema::hasColumn('treatment_combos', 'sessions_count')) {
                $table->unsignedTinyInteger('sessions_count')->default(10)->after('name');
            }
        });

        if (Schema::hasColumn('treatment_combos', 'sessions_count')) {
            DB::table('treatment_combos')->update(['sessions_count' => 10]);
        }
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            if (Schema::hasColumn('treatments', 'package_6_price')) {
                $table->dropColumn('package_6_price');
            }
        });

        Schema::table('treatment_combos', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_combos', 'sessions_count')) {
                $table->dropColumn('sessions_count');
            }
        });
    }
};
