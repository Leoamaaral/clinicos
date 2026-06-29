<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_treatment_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('purchase_type');
            $table->decimal('total_price', 10, 2);
            $table->date('purchased_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('client_treatment_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_treatment_purchase_id')
                ->constrained('client_treatment_purchases', indexName: 'ctp_items_purchase_fk')
                ->cascadeOnDelete();
            $table->foreignId('body_region_id')
                ->constrained('body_regions', indexName: 'ctp_items_region_fk');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('sessions_total')->default(1);
            $table->unsignedSmallInteger('sessions_used')->default(0);
            $table->boolean('combo_no_discount')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_treatment_purchase_items');
        Schema::dropIfExists('client_treatment_purchases');
    }
};
