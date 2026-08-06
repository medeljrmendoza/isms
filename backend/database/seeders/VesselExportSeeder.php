<?php

namespace Database\Seeders;

use App\Models\Vessel;
use App\Models\VesselExports\VesselExport;
use Illuminate\Database\Seeder;

class VesselExportSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $rows = [
            [
                'filename' => 'PST-20260801-093015-006-004-SHORE.zip',
                'vessel_file' => 'PST-20260801-093015-006-004-SHORE.zip',
                'vessel_id' => $pacificStar->id,
                'date_of_export' => '2026-08-01',
                'status' => false,
            ],
            [
                'filename' => 'CVO-20260802-141022-006-004-SHORE.zip',
                'vessel_file' => 'CVO-20260802-141022-006-004-SHORE.zip',
                'vessel_id' => $coralVoyager->id,
                'date_of_export' => '2026-08-02',
                'status' => false,
            ],
        ];

        foreach ($rows as $row) {
            VesselExport::updateOrCreate(['vessel_file' => $row['vessel_file']], $row);
        }
    }
}
