<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            if (DB::getDriverName() === 'sqlite') {
                $table->foreignId('client_treatment_purchase_item_id')
                    ->nullable()
                    ->constrained('client_treatment_purchase_items')
                    ->nullOnDelete();
            } else {
                $table->foreignId('client_treatment_purchase_item_id')
                    ->nullable()
                    ->constrained('client_treatment_purchase_items', indexName: 'appt_trt_ctp_item_fk')
                    ->nullOnDelete();
            }
            $table->timestamps();

            $table->unique(['appointment_id', 'treatment_id']);
        });

        if (! Schema::hasColumn('appointments', 'treatment_id')) {
            return;
        }

        DB::table('appointments')
            ->whereNotNull('treatment_id')
            ->orderBy('id')
            ->each(function (object $appointment) {
                DB::table('appointment_treatments')->insert([
                    'appointment_id' => $appointment->id,
                    'treatment_id' => $appointment->treatment_id,
                    'client_treatment_purchase_item_id' => $appointment->client_treatment_purchase_item_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'client_treatment_purchase_item_id')) {
                $table->dropForeign(
                    DB::getDriverName() === 'sqlite'
                        ? ['client_treatment_purchase_item_id']
                        : 'appt_ctp_item_fk',
                );
            }

            if (Schema::hasColumn('appointments', 'treatment_id')) {
                $table->dropForeign(['treatment_id']);
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('appointments', 'client_treatment_purchase_item_id')
                    ? 'client_treatment_purchase_item_id'
                    : null,
                Schema::hasColumn('appointments', 'treatment_id') ? 'treatment_id' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('treatment_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_treatment_purchase_item_id')
                ->nullable()
                ->after('treatment_id')
                ->constrained('client_treatment_purchase_items', indexName: 'appt_ctp_item_fk')
                ->nullOnDelete();
        });

        DB::table('appointment_treatments')
            ->orderBy('id')
            ->each(function (object $row) {
                DB::table('appointments')
                    ->where('id', $row->appointment_id)
                    ->update([
                        'treatment_id' => $row->treatment_id,
                        'client_treatment_purchase_item_id' => $row->client_treatment_purchase_item_id,
                    ]);
            });

        Schema::dropIfExists('appointment_treatments');
    }
};
