<?php

namespace Database\Seeders;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\MasterReview\MasterReview;
use App\Models\MasterReview\MasterReviewPresent;
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
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.2',
                'review_date' => now()->subDays(10),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'review_description' => 'Reviewed procedure against current operational practice.',
                'review_recommendation' => 'No changes required at this time.',
                'shore_reviewed_by' => 'Capt. Reyes (DPA)',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => '',
            ],
            // Should appear: VESSEL-added and vessel-approved.
            [
                'vessel_id' => $coralVoyager->id,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.5',
                'review_date' => now()->subDays(6),
                'added_by' => 'VESSEL',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'review_description' => 'Crew feedback on the boarding procedure during the last drill.',
                'review_recommendation' => 'Clarify step 3 wording.',
                'vessel_reviewed_by' => 'C/O Santos',
                'vessel_reviewed_position' => 'Chief Officer',
                'vessel_remarks' => 'Submitted after monthly safety meeting.',
                'is_deleted' => false,
                'is_vessel_approved' => true,
                'shore_status' => '',
            ],
            // Should NOT appear: VESSEL-added but not yet vessel-approved.
            [
                'vessel_id' => $pacificStar->id,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.6',
                'review_date' => now()->subDays(4),
                'added_by' => 'VESSEL',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'review_description' => 'Draft submission awaiting vessel-side approval.',
                'review_recommendation' => 'TBD.',
                'vessel_reviewed_by' => 'C/E Cruz',
                'vessel_reviewed_position' => 'Chief Engineer',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => '',
            ],
            // Should NOT appear on the dashboard (already actioned) but does
            // appear in the full module's "APPROVED" filter.
            [
                'vessel_id' => $coralVoyager->id,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.1',
                'review_date' => now()->subMonths(1),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q2',
                'review_year' => now()->year,
                'review_description' => 'Annual review of anchoring procedure.',
                'review_recommendation' => 'Approved as submitted.',
                'shore_reviewed_by' => 'Capt. Reyes (DPA)',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => 'APPROVED',
            ],
            // Full-module only: RECOMMEND APPROVAL, chapter-level review (no specific procedure).
            [
                'vessel_id' => null,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => null,
                'manual_section' => null,
                'review_date' => now()->subDays(15),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'review_description' => 'General review of the Safety Management chapter structure.',
                'review_recommendation' => 'Recommend office head sign-off before closing.',
                'shore_reviewed_by' => 'J. Dela Cruz (Safety Officer)',
                'shore_remarks' => 'Forwarded to DPA for final recommendation.',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => 'RECOMMEND APPROVAL',
            ],
            // Full-module only: UNDER REVIEW.
            [
                'vessel_id' => null,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.3',
                'review_date' => now()->subDays(8),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q3',
                'review_year' => now()->year,
                'review_description' => 'Follow-up review requested after PSC finding referenced this procedure.',
                'review_recommendation' => 'Pending further input from technical department.',
                'shore_reviewed_by' => 'Capt. Reyes (DPA)',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => 'UNDER REVIEW',
            ],
            // Full-module only: DISREGARD.
            [
                'vessel_id' => null,
                'manual_chapter_id' => $chapter->id,
                'manual_document_id' => $doc->id,
                'manual_section' => 'Section 4.4',
                'review_date' => now()->subMonths(2),
                'added_by' => 'SHORE',
                'review_quarter' => 'Q2',
                'review_year' => now()->year,
                'review_description' => 'Duplicate submission of an earlier review.',
                'review_recommendation' => 'N/A — superseded by another record.',
                'shore_reviewed_by' => 'Capt. Reyes (DPA)',
                'is_deleted' => false,
                'is_vessel_approved' => false,
                'shore_status' => 'DISREGARD',
            ],
        ];

        foreach ($reviews as $review) {
            MasterReview::updateOrCreate(
                ['manual_section' => $review['manual_section'], 'review_date' => $review['review_date']],
                $review,
            );
        }

        // Present-during-review attendees for the first (pending, SHORE) record.
        $pendingShoreReview = MasterReview::where('manual_section', 'Section 4.2')->where('review_date', now()->subDays(10))->first();
        if ($pendingShoreReview && $pendingShoreReview->present()->count() === 0) {
            MasterReviewPresent::insert([
                ['master_review_id' => $pendingShoreReview->id, 'arrangement' => 1, 'name' => 'Capt. Reyes', 'position' => 'DPA', 'created_at' => now(), 'updated_at' => now()],
                ['master_review_id' => $pendingShoreReview->id, 'arrangement' => 2, 'name' => 'J. Dela Cruz', 'position' => 'Safety Officer', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
