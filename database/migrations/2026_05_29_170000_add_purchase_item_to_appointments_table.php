<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('client_treatment_purchase_item_id')
                ->nullable()
                ->after('treatment_id')
                ->constrained('client_treatment_purchase_items', indexName: 'appt_ctp_item_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('appt_ctp_item_fk');
            $table->dropColumn('client_treatment_purchase_item_id');
        });
    }
};
