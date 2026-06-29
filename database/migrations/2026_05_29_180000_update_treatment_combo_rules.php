<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('treatment_combos')->update([
            'region_count' => 2,
            'extra_discount_percent' => 10,
        ]);
    }

    public function down(): void
    {
        DB::table('treatment_combos')->update([
            'region_count' => 3,
            'extra_discount_percent' => 15,
        ]);
    }
};
