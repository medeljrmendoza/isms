<?php

namespace Database\Seeders;

use App\Models\ManualChapter;
use App\Models\ManualDocument;
use App\Models\ManualForm;
use App\Models\Vessel;
use App\Models\VesselFormSync;
use App\Models\VesselManualSync;
use Illuminate\Database\Seeder;

class SmsVersionMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $chapter = ManualChapter::firstOrCreate(['reference_no' => '04'], ['chapter_name' => 'Safety Management']);

        // Applies fleet-wide. Pacific Star already synced; Coral Voyager never has.
        $docAll = ManualDocument::firstOrCreate(
            ['manual_chapter_id' => $chapter->id, 'reference_no' => 'SMS-01'],
            ['manual_name' => 'Fleet Safety Circular', 'date_of_revision' => now()->subDays(5), 'is_published' => true, 'vessel_access' => 'ALL', 'file_hash' => 'hash-sms-01-v2'],
        );
        VesselManualSync::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'manual_document_id' => $docAll->id],
            ['file_hash' => 'hash-sms-01-v2'],
        );

        // Restricted to Pacific Star only; its sync is stale (mismatched hash).
        $docSpecific = ManualDocument::firstOrCreate(
            ['manual_chapter_id' => $chapter->id, 'reference_no' => 'SMS-02'],
            ['manual_name' => 'Pacific Star Specific Procedure', 'date_of_revision' => now()->subDays(2), 'is_published' => true, 'vessel_access' => 'SPECIFIC', 'file_hash' => 'hash-sms-02-v3'],
        );
        $docSpecific->vessels()->syncWithoutDetaching([$pacificStar->id]);
        VesselManualSync::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'manual_document_id' => $docSpecific->id],
            ['file_hash' => 'hash-sms-02-v1'],
        );

        // Not published — excluded from the count entirely regardless of sync state.
        ManualDocument::firstOrCreate(
            ['manual_chapter_id' => $chapter->id, 'reference_no' => 'SMS-03'],
            ['manual_name' => 'Draft Procedure', 'date_of_revision' => now()->subDays(1), 'is_published' => false, 'vessel_access' => 'ALL', 'file_hash' => 'hash-sms-03-v1'],
        );

        // Fleet-wide form. Pacific Star synced; Coral Voyager is stale.
        $formAll = ManualForm::firstOrCreate(
            ['reference_no' => 'F-01'],
            ['file_name' => 'Permit to Work Form', 'is_active' => true, 'is_deleted' => false, 'vessel_access' => 'ALL', 'file_hash' => 'fhash-f01-v2'],
        );
        VesselFormSync::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'manual_form_id' => $formAll->id],
            ['file_hash' => 'fhash-f01-v2'],
        );
        VesselFormSync::updateOrCreate(
            ['vessel_id' => $coralVoyager->id, 'manual_form_id' => $formAll->id],
            ['file_hash' => 'fhash-f01-v1'],
        );

        // Deleted / inactive forms are excluded regardless of sync state.
        ManualForm::firstOrCreate(
            ['reference_no' => 'F-02'],
            ['file_name' => 'Retired Form', 'is_active' => true, 'is_deleted' => true, 'vessel_access' => 'ALL', 'file_hash' => 'fhash-f02-v1'],
        );
        ManualForm::firstOrCreate(
            ['reference_no' => 'F-03'],
            ['file_name' => 'Suspended Form', 'is_active' => false, 'is_deleted' => false, 'vessel_access' => 'ALL', 'file_hash' => 'fhash-f03-v1'],
        );
    }
}
