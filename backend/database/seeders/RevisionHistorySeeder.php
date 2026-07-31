<?php

namespace Database\Seeders;

use App\Models\RevisionHistory\ManualRevision;
use Illuminate\Database\Seeder;

class RevisionHistorySeeder extends Seeder
{
    public function run(): void
    {
        ManualRevision::query()->delete();

        $rows = [
            // manual_document_id 3 => (04.01) Risk Assessment Procedure, chapter 1
            ['manual_document_id' => 3, 'arrangement' => 1, 'revision_no' => 'REV-00', 'date_revised' => '2024-01-15', 'section' => '4.1', 'reason_revision' => 'Initial issue.', 'reviewed_by' => 'J. Santos', 'approved_by' => 'M. Reyes'],
            ['manual_document_id' => 3, 'arrangement' => 2, 'revision_no' => 'REV-01', 'date_revised' => '2025-03-10', 'section' => '4.1', 'reason_revision' => 'Updated risk matrix scoring to align with company policy update.', 'reviewed_by' => 'J. Santos', 'approved_by' => 'M. Reyes'],
            ['manual_document_id' => 3, 'arrangement' => 3, 'revision_no' => 'REV-02', 'date_revised' => '2026-06-20', 'section' => '4.3', 'reason_revision' => 'Clarified escalation steps for high-risk findings.', 'reviewed_by' => 'A. Cruz', 'approved_by' => 'M. Reyes'],

            // manual_document_id 1 => (04.02) Permit to Work Procedure, chapter 1
            ['manual_document_id' => 1, 'arrangement' => 1, 'revision_no' => 'REV-00', 'date_revised' => '2024-02-01', 'section' => null, 'reason_revision' => 'Initial issue.', 'reviewed_by' => 'J. Santos', 'approved_by' => 'M. Reyes'],
            ['manual_document_id' => 1, 'arrangement' => 2, 'revision_no' => 'REV-01', 'date_revised' => '2026-05-05', 'section' => '2.2', 'reason_revision' => 'Added hot-work permit sign-off requirement.', 'reviewed_by' => 'A. Cruz', 'approved_by' => 'R. Villanueva'],

            // manual_document_id 4 => (SMS-01) Fleet Safety Circular, chapter 1
            ['manual_document_id' => 4, 'arrangement' => 1, 'revision_no' => 'REV-00', 'date_revised' => '2025-11-12', 'section' => '1.0', 'reason_revision' => 'Initial issue covering fleet-wide safety circular numbering.', 'reviewed_by' => 'R. Villanueva', 'approved_by' => 'M. Reyes'],

            // manual_document_id 2 => (07.01) Emergency Response Plan, chapter 2
            ['manual_document_id' => 2, 'arrangement' => 1, 'revision_no' => 'REV-00', 'date_revised' => '2024-04-18', 'section' => '3.1', 'reason_revision' => 'Initial issue.', 'reviewed_by' => 'A. Cruz', 'approved_by' => 'R. Villanueva'],
            ['manual_document_id' => 2, 'arrangement' => 2, 'revision_no' => 'REV-01', 'date_revised' => '2026-01-30', 'section' => '3.4', 'reason_revision' => 'Revised muster station diagram and contact tree.', 'reviewed_by' => 'A. Cruz', 'approved_by' => 'R. Villanueva'],
            ['manual_document_id' => 2, 'arrangement' => 3, 'revision_no' => 'REV-02', 'date_revised' => '2026-07-22', 'section' => '3.4', 'reason_revision' => 'Updated helicopter evacuation coordination contact.', 'reviewed_by' => 'J. Santos', 'approved_by' => 'R. Villanueva'],
        ];

        foreach ($rows as $row) {
            ManualRevision::create($row);
        }
    }
}
