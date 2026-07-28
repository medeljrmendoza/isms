<?php

namespace Database\Seeders;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\MasterReview\MasterReview;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class MasterReviewSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $chapter = ManualChapter::firstOrCreate(['reference_no' => '04'], ['chapter_name' => 'Safety Management']);
        $doc = ManualDocument::firstOrCreate(
            ['manual_chapter_id' => $chapter->id, 'reference_no' => '04.01'],
            ['manual_name' => 'Risk Assessment Procedure', 'date_of_revision' => now()->subMonths(2), 'is_published' => true],
        );

        $reviews = [
            // Should appear: SHORE-added, still pending shore action.
            [
                'vessel_id' => $pacificStar->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.2',
                'review_date' => now()->subDays(10),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => '',
            ],
            // Should appear: VESSEL-added and vessel-approved.
            [
                'vessel_id' => $coralVoyager->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.5',
                'review_date' => now()->subDays(6),
                'added_by' => 'VESSEL',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'is_deleted' => false,
                'is_vessel_approved' => true,
                'shore_status' => '',
            ],
            // Should NOT appear: VESSEL-added but not yet vessel-approved.
            [
                'vessel_id' => $pacificStar->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.6',
                'review_date' => now()->subDays(4),
                'added_by' => 'VESSEL',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => '',
            ],
            // Should NOT appear: already actioned by shore.
            [
                'vessel_id' => $coralVoyager->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.1',
                'review_date' => now()->subMonths(1),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q2',
                'review_year' => now()->year,
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => 'APPROVED',
            ],
        ];

        foreach ($reviews as $review) {
            MasterReview::updateOrCreate(
                ['vessel_id' => $review['vessel_id'], 'manual_section' => $review['manual_section'], 'review_date' => $review['review_date']],
                $review,
            );
        }
    }
}
