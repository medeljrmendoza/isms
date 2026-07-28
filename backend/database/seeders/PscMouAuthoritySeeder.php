<?php

namespace Database\Seeders;

use App\Models\PscReports\PscMouAuthority;
use Illuminate\Database\Seeder;

class PscMouAuthoritySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Paris MOU',
            'Tokyo MOU',
            'USCG',
            'Indian Ocean MOU',
            'Caribbean MOU',
            'Mediterranean MOU',
            'Black Sea MOU',
            'Abuja MOU',
            'Riyadh MOU',
            'Others',
        ];

        foreach ($names as $name) {
            PscMouAuthority::firstOrCreate(['name' => $name]);
        }
    }
}
