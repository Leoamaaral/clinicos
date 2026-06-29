<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('anamnesis_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anamnesis_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('anamnesis_questions')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anamnesis_answers');
        Schema::dropIfExists('anamnesis_records');
    }
};
