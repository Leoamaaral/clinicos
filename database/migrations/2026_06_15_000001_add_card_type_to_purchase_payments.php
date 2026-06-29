<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_treatment_purchase_payments', function (Blueprint $table) {
            $table->string('card_type')->nullable()->after('installments');
        });
    }

    public function down(): void
    {
        Schema::table('client_treatment_purchase_payments', function (Blueprint $table) {
            $table->dropColumn('card_type');
        });
    }
};
