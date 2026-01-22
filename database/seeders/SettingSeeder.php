<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'price_mobile',
                'label' => 'Precio Minuto Celular',
                'value' => 80,
            ],
            [
                'key' => 'price_national',
                'label' => 'Precio Fijo Nacional',
                'value' => 40,
            ],
            [
                'key' => 'price_international',
                'label' => 'Precio Internacional',
                'value' => 500,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
