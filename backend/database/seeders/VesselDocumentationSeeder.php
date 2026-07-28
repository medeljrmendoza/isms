<?php

namespace Database\Seeders;

use App\Models\Vessel;
use App\Models\VesselDocument;
use App\Models\VesselDocumentExpirySetting;
use App\Models\VesselDocumentRecord;
use App\Models\VesselDocumentType;
use Illuminate\Database\Seeder;

class VesselDocumentationSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        VesselDocumentExpirySetting::query()->firstOrCreate([], ['num_month' => 3]);

        $certificates = VesselDocumentType::firstOrCreate(['name' => 'Certificates'], ['is_active' => true, 'is_deleted' => false]);

        $smc = VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $certificates->id, 'name' => 'Safety Management Certificate'],
            ['is_active' => true, 'is_deleted' => false],
        );
        $classCert = VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $certificates->id, 'name' => 'Class Certificate'],
            ['is_active' => true, 'is_deleted' => false],
        );
        $discontinued = VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $certificates->id, 'name' => 'Discontinued Certificate'],
            ['is_active' => false, 'is_deleted' => false],
        );

        $records = [
            // Pacific Star: expired, no hash activity.
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $smc->id,
                'date_issued' => now()->subYears(5), 'date_expired' => now()->subDays(5),
                'date_range_from' => null, 'date_range_to' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
            ],
            // Pacific Star: expiring within the 3-month window, and both sides disagree
            // on the attachment hash — counts toward both "new from vessel" and "new from shore".
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $classCert->id,
                'date_issued' => now()->subYears(1), 'date_expired' => now()->addMonths(2),
                'date_range_from' => null, 'date_range_to' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => 'vhash-new', 'shore_file_hash' => 'shash-old',
            ],
            // Pacific Star: excluded — catalog entry itself is inactive, despite looking expired.
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $discontinued->id,
                'date_issued' => now()->subYears(3), 'date_expired' => now()->subDays(1),
                'date_range_from' => null, 'date_range_to' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
            ],
            // Coral Voyager: never expires, hashes match — no counts at all.
            [
                'vessel_id' => $coralVoyager->id, 'vessel_document_id' => $smc->id,
                'date_issued' => now()->subYears(2), 'date_expired' => null,
                'date_range_from' => null, 'date_range_to' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => 'same-hash', 'shore_file_hash' => 'same-hash',
            ],
            // Coral Voyager: far from expiring, but shore has an attachment the vessel
            // has never uploaded anything for — only "new from shore" should count.
            [
                'vessel_id' => $coralVoyager->id, 'vessel_document_id' => $classCert->id,
                'date_issued' => now()->subMonths(6), 'date_expired' => now()->addMonths(12),
                'date_range_from' => null, 'date_range_to' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => 'shash-shore-only',
            ],
        ];

        foreach ($records as $record) {
            VesselDocumentRecord::updateOrCreate(
                ['vessel_id' => $record['vessel_id'], 'vessel_document_id' => $record['vessel_document_id']],
                $record,
            );
        }
    }
}
