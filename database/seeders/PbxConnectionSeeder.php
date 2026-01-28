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
                'name' => 'Central Principal',
                'ip' => '10.36.1.10',
                'port' => 7110,
                'username' => 'cdrapi',
                'password' => '123api',
                'verify_ssl' => false,
            ]
        );
    }
}
