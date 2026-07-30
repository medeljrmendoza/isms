<?php

namespace Database\Seeders;

use App\Models\Vessel;
use App\Models\VesselDocumentation\VesselDocument;
use App\Models\VesselDocumentation\VesselDocumentExpirySetting;
use App\Models\VesselDocumentation\VesselDocumentRecord;
use App\Models\VesselDocumentation\VesselDocumentType;
use Illuminate\Database\Seeder;

class VesselDocumentationSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);
        $northernLight = Vessel::firstOrCreate(['name' => 'Northern Light'], ['prefix' => 'MV']);

        VesselDocumentExpirySetting::query()->firstOrCreate([], ['num_month' => 3]);

        $certificates = VesselDocumentType::firstOrCreate(['name' => 'Certificates'], ['is_active' => true, 'is_deleted' => false]);
        $manuals = VesselDocumentType::firstOrCreate(['name' => 'Manuals'], ['is_active' => true, 'is_deleted' => false]);

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
        // No record seeded for any vessel — stays available in the Add form's
        // document picker, demonstrating catalogOptionsForVessel().
        VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $certificates->id, 'name' => 'Loadline Certificate'],
            ['is_active' => true, 'is_deleted' => false],
        );
        $cargoManual = VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $manuals->id, 'name' => 'Cargo Securing Manual'],
            ['is_active' => true, 'is_deleted' => false],
        );
        $garbageManual = VesselDocument::firstOrCreate(
            ['vessel_document_type_id' => $manuals->id, 'name' => 'Garbage Management Plan'],
            ['is_active' => true, 'is_deleted' => false],
        );

        $records = [
            // Pacific Star: expired, no hash activity.
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $smc->id,
                'doc_number' => 'SMC-2023-PST', 'issuing_body' => 'Flag Administration',
                'date_issued' => now()->subYears(5), 'date_expired' => now()->subDays(5),
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => true,
                'shore_remarks' => 'Renewal application submitted, awaiting surveyor.', 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
            ],
            // Pacific Star: expiring within the 3-month window, and both sides disagree
            // on the attachment hash — counts toward both "new from vessel" and "new from shore".
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $classCert->id,
                'doc_number' => 'CLS-8841', 'issuing_body' => 'Classification Society',
                'date_issued' => now()->subYears(1), 'date_expired' => now()->addMonths(2),
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => true,
                'shore_remarks' => null, 'vessel_remarks' => 'Survey booked for next port call.',
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => 'vhash-new', 'shore_file_hash' => 'shash-old',
            ],
            // Pacific Star: excluded — catalog entry itself is inactive, despite looking expired.
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $discontinued->id,
                'doc_number' => 'OLD-001', 'issuing_body' => 'Legacy Authority',
                'date_issued' => now()->subYears(3), 'date_expired' => now()->subDays(1),
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => false,
                'shore_remarks' => null, 'vessel_remarks' => null,
                'is_active' => false, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
            ],
            // Pacific Star: Manuals type, comfortably valid.
            [
                'vessel_id' => $pacificStar->id, 'vessel_document_id' => $cargoManual->id,
                'doc_number' => null, 'issuing_body' => 'Company',
                'date_issued' => now()->subMonths(8), 'date_expired' => null,
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => false,
                'shore_remarks' => null, 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => 'same-hash', 'shore_file_hash' => 'same-hash',
            ],
            // Coral Voyager: never expires, hashes match — no counts at all.
            [
                'vessel_id' => $coralVoyager->id, 'vessel_document_id' => $smc->id,
                'doc_number' => 'SMC-2024-CVG', 'issuing_body' => 'Flag Administration',
                'date_issued' => now()->subYears(2), 'date_expired' => null,
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => true,
                'shore_remarks' => null, 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => 'same-hash', 'shore_file_hash' => 'same-hash',
            ],
            // Coral Voyager: far from expiring, but shore has an attachment the vessel
            // has never uploaded anything for — only "new from shore" should count.
            [
                'vessel_id' => $coralVoyager->id, 'vessel_document_id' => $classCert->id,
                'doc_number' => 'CLS-7720', 'issuing_body' => 'Classification Society',
                'date_issued' => now()->subMonths(6), 'date_expired' => now()->addMonths(12),
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => true,
                'shore_remarks' => 'Latest copy uploaded by shore, vessel yet to acknowledge.', 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => 'shash-shore-only',
            ],
            // Coral Voyager: Manuals type, expired.
            [
                'vessel_id' => $coralVoyager->id, 'vessel_document_id' => $garbageManual->id,
                'doc_number' => null, 'issuing_body' => 'Company',
                'date_issued' => now()->subYears(4), 'date_expired' => now()->subMonths(1),
                'date_range_from' => null, 'date_range_to' => null, 'is_printer_friendly' => false,
                'shore_remarks' => 'Revision overdue — new plan being drafted.', 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
            ],
            // Northern Light: within a date-range validity window (range-based expiry, not a hard date).
            [
                'vessel_id' => $northernLight->id, 'vessel_document_id' => $smc->id,
                'doc_number' => 'SMC-2025-NLT', 'issuing_body' => 'Flag Administration',
                'date_issued' => now()->subMonths(10), 'date_expired' => now()->addMonths(4),
                'date_range_from' => now()->subDays(20), 'date_range_to' => now()->addDays(10),
                'is_printer_friendly' => true,
                'shore_remarks' => null, 'vessel_remarks' => null,
                'is_active' => true, 'is_deleted' => false,
                'vessel_file_hash' => null, 'shore_file_hash' => null,
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
