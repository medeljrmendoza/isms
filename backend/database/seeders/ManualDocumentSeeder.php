<?php

namespace Database\Seeders;

use App\Models\ManualChapter;
use App\Models\ManualDocument;
use Illuminate\Database\Seeder;

class ManualDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $safety = ManualChapter::firstOrCreate(['reference_no' => '04'], ['chapter_name' => 'Safety Management']);
        $emergency = ManualChapter::firstOrCreate(['reference_no' => '07'], ['chapter_name' => 'Emergency Preparedness']);

        $documents = [
            // Should appear: not yet published.
            [
                'manual_chapter_id' => $safety->id,
                'reference_no' => '04.02',
                'manual_name' => 'Permit to Work Procedure',
                'date_of_revision' => now()->subDays(3),
                'is_published' => false,
            ],
            // Should appear: not yet published.
            [
                'manual_chapter_id' => $emergency->id,
                'reference_no' => '07.01',
                'manual_name' => 'Emergency Response Plan',
                'date_of_revision' => now()->subDays(1),
                'is_published' => false,
            ],
            // Should NOT appear: already published.
            [
                'manual_chapter_id' => $safety->id,
                'reference_no' => '04.01',
                'manual_name' => 'Risk Assessment Procedure',
                'date_of_revision' => now()->subMonths(2),
                'is_published' => true,
            ],
        ];

        foreach ($documents as $document) {
            ManualDocument::updateOrCreate(
                ['manual_chapter_id' => $document['manual_chapter_id'], 'reference_no' => $document['reference_no']],
                $document,
            );
        }
    }
}
