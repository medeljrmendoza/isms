<?php

namespace Database\Seeders;

use App\Models\ManualPublish\ManualDocument;
use App\Models\ManualPublish\ManualForm;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Links the ManualForm rows already created by SmsVersionMonitoringSeeder
 * to specific documents, and adds one SPECIFIC-access form, so the
 * Manuals browser has something realistic to nest under each document.
 */
class ManualBrowserSeeder extends Seeder
{
    public function run(): void
    {
        $riskAssessment = ManualDocument::where('reference_no', '04.01')->first();
        $smsOne = ManualDocument::where('reference_no', 'SMS-01')->first();
        $smsTwo = ManualDocument::where('reference_no', 'SMS-02')->first();

        // Active, ALL-access form nested under a published ALL-access document.
        ManualForm::where('reference_no', 'F-01')->update(['manual_document_id' => $riskAssessment?->id]);

        // Deleted form nested under a visible document — should never appear despite the document showing.
        ManualForm::where('reference_no', 'F-02')->update(['manual_document_id' => $smsOne?->id]);

        // Inactive form nested under a visible document — should never appear either.
        ManualForm::where('reference_no', 'F-03')->update(['manual_document_id' => $smsOne?->id]);

        if ($smsTwo) {
            $pacificStar = Vessel::where('name', 'Pacific Star')->first();

            $specificForm = ManualForm::firstOrCreate(
                ['reference_no' => 'F-04'],
                [
                    'manual_document_id' => $smsTwo->id,
                    'file_name' => 'Pacific Star Specific Attachment',
                    'is_active' => true,
                    'is_deleted' => false,
                    'vessel_access' => 'SPECIFIC',
                    'file_hash' => 'fhash-f04-v1',
                ],
            );

            if ($pacificStar) {
                $specificForm->vessels()->syncWithoutDetaching([$pacificStar->id]);
            }
        }
    }
}
