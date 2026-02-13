<?php

namespace Database\Seeders;

use App\Models\PbxConnection;
use Illuminate\Database\Seeder;

class PbxConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PbxConnection::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'UCM',
                'ip' => '12.34.56.78',
                'port' => 9999,
                'username' => 'username',
                'password' => 'password',
                'verify_ssl' => false,
            ]
        );
    }
}
