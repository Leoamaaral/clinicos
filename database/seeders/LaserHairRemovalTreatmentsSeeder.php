<?php

namespace Database\Seeders;

use App\Models\Treatment;
use Illuminate\Database\Seeder;

class LaserHairRemovalTreatmentsSeeder extends Seeder
{
    /**
     * Pacotes = 6 ou 10 sessões. Valores de pacote na tabela são por sessão.
     *
     * @var array<int, array{region: string, single_price: float, package_per_session: float}>
     */
    private const REGIONS = [
        ['region' => 'QUEIXO', 'single_price' => 100, 'package_per_session' => 75, 'minutes' => 15],
        ['region' => 'BUÇO', 'single_price' => 100, 'package_per_session' => 75, 'minutes' => 15],
        ['region' => 'ORELHA', 'single_price' => 100, 'package_per_session' => 75, 'minutes' => 15],
        ['region' => 'BARBA', 'single_price' => 250, 'package_per_session' => 190, 'minutes' => 30],
        ['region' => 'AXILA', 'single_price' => 170, 'package_per_session' => 120, 'minutes' => 30],
        ['region' => 'PEITO', 'single_price' => 350, 'package_per_session' => 290, 'minutes' => 45],
        ['region' => 'COSTAS', 'single_price' => 350, 'package_per_session' => 290, 'minutes' => 45],
        ['region' => 'BRAÇO (MEIO)', 'single_price' => 250, 'package_per_session' => 190, 'minutes' => 45],
        ['region' => 'BRAÇO (INTEIRO)', 'single_price' => 350, 'package_per_session' => 290, 'minutes' => 45],
        ['region' => 'LINHA ALBA', 'single_price' => 140, 'package_per_session' => 100, 'minutes' => 15],
        ['region' => 'GLUTEO', 'single_price' => 190, 'package_per_session' => 140, 'minutes' => 30],
        ['region' => 'Virilha', 'single_price' => 190, 'package_per_session' => 140, 'minutes' => 30],
        ['region' => 'PERIANAL', 'single_price' => 100, 'package_per_session' => 70, 'minutes' => 15],
        ['region' => 'PERNA (INTEIRA)', 'single_price' => 390, 'package_per_session' => 350, 'minutes' => 60],
        ['region' => 'PERNA (MEIA)', 'single_price' => 190, 'package_per_session' => 170, 'minutes' => 45],
    ];

    public function run(): void
    {
        foreach (self::REGIONS as $row) {
            Treatment::query()->updateOrCreate(
                ['name' => 'Depilação a laser - '.$row['region']],
                [
                    'single_price' => $row['single_price'],
                    'package_6_price' => $row['package_per_session'] * 6,
                    'package_price' => $row['package_per_session'] * 10,
                    'duration_minutes' => $row['minutes'],
                    'is_active' => true,
                ],
            );
        }
    }
}
