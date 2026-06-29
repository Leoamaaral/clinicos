<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(60)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
