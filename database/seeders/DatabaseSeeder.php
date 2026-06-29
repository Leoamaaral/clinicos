<?php

namespace Database\Seeders;

use App\Models\AnamnesisQuestion;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'clinicos@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('@password123'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );

        ClinicSetting::current();

        $questions = [
            ['question' => 'Já fez depilação?', 'type' => 'checkbox', 'order' => 1],
            ['question' => 'Tem alergia a algum cosmético/medicamento?', 'type' => 'checkbox', 'order' => 2],
            ['question' => 'Problemas de pele?', 'type' => 'checkbox', 'order' => 3],
            ['question' => 'Está em tratamento dermatológico?', 'type' => 'checkbox', 'order' => 4],
            ['question' => 'Tipo de pele?', 'type' => 'select', 'options' => ['Oleosa', 'Normal', 'Seca'], 'order' => 5],
            ['question' => 'Está grávida?', 'type' => 'checkbox', 'order' => 6],
            ['question' => 'Faz uso de algum medicamento?', 'type' => 'checkbox', 'order' => 7],
            ['question' => 'Realizou alguma cirurgia recente?', 'type' => 'checkbox', 'order' => 8],
            ['question' => 'Foliculite?', 'type' => 'checkbox', 'order' => 9],
            ['question' => 'Fez bronzeamento recentemente?', 'type' => 'checkbox', 'order' => 10],
            ['question' => 'Usa ácidos ou clareadores?', 'type' => 'checkbox', 'order' => 11],
            [
                'question' => 'Foto tipo de pele:',
                'type' => 'select',
                'options' => [
                    'I - Muito clara',
                    'II - Clara',
                    'III - Morena clara',
                    'IV - Morena moderada',
                    'V - Morena escura',
                    'VI - Negra',
                ],
                'order' => 12,
            ],
            [
                'question' => 'Algum outro problema que seja necessário nos informar antes do procedimento?',
                'type' => 'checkbox',
                'order' => 13,
            ],
        ];

        foreach ($questions as $question) {
            AnamnesisQuestion::create($question);
        }

        $this->call(TreatmentPricingSeeder::class);
        $this->call(LaserHairRemovalTreatmentsSeeder::class);
    }
}
