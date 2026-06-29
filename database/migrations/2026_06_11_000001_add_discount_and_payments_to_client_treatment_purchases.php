<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_treatment_purchases', function (Blueprint $table) {
            $table->decimal('calculated_price', 10, 2)->nullable()->after('purchase_type');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('calculated_price');
        });

        Schema::create('client_treatment_purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_treatment_purchase_id')
                ->constrained('client_treatment_purchases', indexName: 'ctp_payments_purchase_fk')
                ->cascadeOnDelete();
            $table->string('method');
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('installments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_treatment_purchase_payments');

        Schema::table('client_treatment_purchases', function (Blueprint $table) {
            $table->dropColumn(['calculated_price', 'discount_percent']);
        });
    }
};
