<?php

namespace Database\Seeders;

use App\Models\TreatmentCombo;
use Illuminate\Database\Seeder;

class TreatmentPricingSeeder extends Seeder
{
    public function run(): void
    {
        TreatmentCombo::query()->updateOrCreate(
            ['name' => 'Combo pacote 6 sessões'],
            [
                'sessions_count' => 6,
                'min_treatment_count' => 2,
                'extra_discount_percent' => 10,
                'is_active' => true,
            ],
        );

        TreatmentCombo::query()->updateOrCreate(
            ['name' => 'Combo pacote 10 sessões'],
            [
                'sessions_count' => 10,
                'min_treatment_count' => 2,
                'extra_discount_percent' => 10,
                'is_active' => true,
            ],
        );
    }
}
