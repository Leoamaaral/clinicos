<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_treatment_purchases', function (Blueprint $table) {
            $table->boolean('is_courtesy')->default(false)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('client_treatment_purchases', function (Blueprint $table) {
            $table->dropColumn('is_courtesy');
        });
    }
};
