<?php

namespace Database\Seeders;

use App\Models\IspsReview\IspsReview;
use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class IspsReviewSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $chapter = ManualChapter::firstOrCreate(['reference_no' => '07'], ['chapter_name' => 'Emergency Preparedness']);
        $doc = ManualDocument::firstOrCreate(
            ['manual_chapter_id' => $chapter->id, 'reference_no' => '07.01'],
            ['manual_name' => 'Emergency Response Plan', 'date_of_revision' => now()->subDays(1), 'is_published' => false],
        );

        $reviews = [
            // Should appear: SHORE-added, still pending shore action.
            [
                'vessel_id' => $pacificStar->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 7.2',
                'review_date' => now()->subDays(8),
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
                'manual_section' => 'Section 7.4',
                'review_date' => now()->subDays(3),
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
                'manual_section' => 'Section 7.5',
                'review_date' => now()->subDays(2),
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
                'manual_section' => 'Section 7.1',
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
            IspsReview::updateOrCreate(
                ['vessel_id' => $review['vessel_id'], 'manual_section' => $review['manual_section'], 'review_date' => $review['review_date']],
                $review,
            );
        }
    }
}
