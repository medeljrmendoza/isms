<?php

namespace Database\Seeders;

use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CommitteeMeetings\CommitteeMeetingType;
use App\Models\CompanyDocumentation\CompanyDocument;
use App\Models\CompanyDocumentation\CompanyDocumentationRecord;
use App\Models\CompanyDocumentation\CompanyDocumentExpirySetting;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class CommitteeMeetingCompanyDocsSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // --- Committee Meeting ---
        $safety = CommitteeMeetingType::firstOrCreate(['name' => 'SAFETY']);
        $others = CommitteeMeetingType::firstOrCreate(['name' => 'OTHERS']);

        $meeting1 = CommitteeMeeting::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'meeting_date' => '2026-07-15'],
            [
                'added_by' => 'SHORE',
                'is_published' => true,
                'is_approved' => false,
                'is_deleted' => false,
                'shore_remarks' => '',
            ],
        );
        $meeting1->meetingTypes()->sync([$safety->id]);

        $meeting2 = CommitteeMeeting::updateOrCreate(
            ['vessel_id' => $coralVoyager->id, 'meeting_date' => '2026-07-18'],
            [
                'added_by' => 'VESSEL',
                'is_published' => false,
                'is_approved' => false,
                'is_deleted' => false,
                'shore_remarks' => '',
            ],
        );
        $meeting2->meetingTypes()->sync([$others->id => ['type_other' => 'Crew Welfare Discussion']]);

        // Excluded: SHORE but unpublished — the SHORE branch requires published.
        $meeting3 = CommitteeMeeting::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'meeting_date' => '2026-06-20'],
            [
                'added_by' => 'SHORE',
                'is_published' => false,
                'is_approved' => false,
                'is_deleted' => false,
                'shore_remarks' => '',
            ],
        );
        $meeting3->meetingTypes()->sync([$safety->id]);

        // Excluded: already has shore remarks and is approved.
        $meeting4 = CommitteeMeeting::updateOrCreate(
            ['vessel_id' => $coralVoyager->id, 'meeting_date' => '2026-06-25'],
            [
                'added_by' => 'SHORE',
                'is_published' => true,
                'is_approved' => true,
                'is_deleted' => false,
                'shore_remarks' => 'Reviewed, no issues.',
            ],
        );
        $meeting4->meetingTypes()->sync([$safety->id]);

        // --- Company Documentation ---
        CompanyDocumentExpirySetting::query()->firstOrCreate([], ['num_month' => 3]);

        $ismDoc = CompanyDocument::firstOrCreate(['name' => 'ISM Document of Compliance'], ['is_active' => true]);
        $safetyCert = CompanyDocument::firstOrCreate(['name' => 'Safety Management Certificate'], ['is_active' => true]);
        $inactiveDoc = CompanyDocument::firstOrCreate(['name' => 'Discontinued Document Type'], ['is_active' => false]);

        $records = [
            // Should appear: already expired.
            [
                'company_document_id' => $ismDoc->id,
                'date_issued' => now()->subYears(5),
                'date_expired' => now()->subDays(10),
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => true,
                'is_deleted' => false,
            ],
            // Should appear: expires within the 3-month warn window.
            [
                'company_document_id' => $safetyCert->id,
                'date_issued' => now()->subYears(4),
                'date_expired' => now()->addMonth(),
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => true,
                'is_deleted' => false,
            ],
            // Should appear: expires far in the future, but today falls inside a custom warning window.
            [
                'company_document_id' => $safetyCert->id,
                'date_issued' => now()->subYears(2),
                'date_expired' => now()->addMonths(6),
                'date_range_from' => now()->subDays(5),
                'date_range_to' => now()->addDays(5),
                'is_active' => true,
                'is_deleted' => false,
            ],

            // Should NOT appear: expires far in the future, no custom window.
            [
                'company_document_id' => $ismDoc->id,
                'date_issued' => now()->subYear(),
                'date_expired' => now()->addMonths(12),
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => true,
                'is_deleted' => false,
            ],
            // Should NOT appear: never expires.
            [
                'company_document_id' => $safetyCert->id,
                'date_issued' => now()->subYears(3),
                'date_expired' => null,
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => true,
                'is_deleted' => false,
            ],
            // Should NOT appear: record itself inactive, even though expired.
            [
                'company_document_id' => $ismDoc->id,
                'date_issued' => now()->subYears(6),
                'date_expired' => now()->subDays(30),
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => false,
                'is_deleted' => false,
            ],
            // Should NOT appear: document catalog entry itself inactive.
            [
                'company_document_id' => $inactiveDoc->id,
                'date_issued' => now()->subYears(6),
                'date_expired' => now()->subDays(30),
                'date_range_from' => null,
                'date_range_to' => null,
                'is_active' => true,
                'is_deleted' => false,
            ],
        ];

        foreach ($records as $record) {
            CompanyDocumentationRecord::updateOrCreate(
                ['company_document_id' => $record['company_document_id'], 'date_issued' => $record['date_issued']],
                $record,
            );
        }
    }
}
