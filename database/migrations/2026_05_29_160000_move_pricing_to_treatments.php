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
            $table->decimal('single_price', 10, 2)->nullable()->after('description');
            $table->decimal('package_price', 10, 2)->nullable()->after('single_price');
        });

        if (Schema::hasColumn('treatments', 'price')) {
            DB::table('treatments')->update([
                'single_price' => DB::raw('price'),
                'package_price' => DB::raw('price * 8'),
            ]);

            Schema::table('treatments', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }

        Schema::table('body_regions', function (Blueprint $table) {
            $table->dropColumn(['single_price', 'package_price']);
        });

        Schema::table('client_treatment_purchase_items', function (Blueprint $table) {
            $table->foreignId('treatment_id')
                ->nullable()
                ->after('client_treatment_purchase_id')
                ->constrained('treatments', indexName: 'ctp_items_treatment_fk');
        });

        $items = DB::table('client_treatment_purchase_items')->get();
        foreach ($items as $item) {
            $treatmentId = DB::table('treatments')
                ->where('body_region_id', $item->body_region_id)
                ->value('id');

            if ($treatmentId) {
                DB::table('client_treatment_purchase_items')
                    ->where('id', $item->id)
                    ->update(['treatment_id' => $treatmentId]);
            }
        }

        Schema::table('client_treatment_purchase_items', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['body_region_id']);
            } else {
                $table->dropForeign('ctp_items_region_fk');
            }
            $table->dropColumn('body_region_id');
        });
    }

    public function down(): void
    {
        Schema::table('body_regions', function (Blueprint $table) {
            $table->decimal('single_price', 10, 2)->default(0)->after('name');
            $table->decimal('package_price', 10, 2)->default(0)->after('single_price');
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('description');
        });

        DB::table('treatments')->update([
            'price' => DB::raw('single_price'),
        ]);

        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn(['single_price', 'package_price']);
        });

        Schema::table('client_treatment_purchase_items', function (Blueprint $table) {
            $table->foreignId('body_region_id')->nullable()->constrained();
        });

        Schema::table('client_treatment_purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_id');
        });
    }
};
