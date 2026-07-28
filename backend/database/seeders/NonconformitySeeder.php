<?php

namespace Database\Seeders;

use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Sample data exercising every branch of the legacy filter ported to
 * NonconformityRepository::pending() — five rows that should show on the
 * dashboard, four that shouldn't, each labeled with why — plus enough
 * full-record detail (reporter, source, root cause, corrective action,
 * verification, close-out) on some rows to exercise the full module's
 * list columns, view modal, and edit form.
 */
class NonconformitySeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $rows = [
            // --- Should appear ---
            [
                'ncr_no' => 'NC-3001',
                'date_of_nc' => '2026-07-01',
                'vessel_id' => $pacificStar->id,
                'vessel_company' => 'VESSEL',
                'department_name' => 'Deck',
                'reported_by' => 'VESSEL',
                'reporter_name' => 'Capt. R. Alonzo',
                'description' => 'Fire extinguisher inspection overdue',
                'added_by' => 'VESSEL',
                'source_of_nc' => 'OPERATIONAL',
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => null,
            ],
            [
                'ncr_no' => 'NC-3002',
                'date_of_nc' => '2026-06-20',
                'vessel_id' => $coralVoyager->id,
                'vessel_company' => 'VESSEL',
                'department_name' => 'Deck',
                'reported_by' => 'VESSEL',
                'reporter_name' => 'C/O J. Reyes',
                'description' => 'Lifeboat davit lubrication needed',
                'added_by' => 'VESSEL',
                'source_of_nc' => 'OPERATIONAL',
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => '2026-06-25',
            ],
            // Fully filled out: root cause, corrective action, verification, close-out —
            // exercises every section of the view modal / edit form at once.
            [
                'ncr_no' => 'NC-3003',
                'date_of_nc' => '2026-07-05',
                'vessel_id' => $pacificStar->id,
                'vessel_company' => 'VESSEL',
                'department_name' => 'Safety',
                'reported_by' => 'SHORE',
                'reporter_name' => 'M. Santos (DPA)',
                'description' => 'Draft SMS deviation report',
                'added_by' => 'SHORE',
                'source_of_nc' => 'OTHERS',
                'source_of_nc_others' => 'Internal safety walk-through',
                'sms_details' => 'Deviation noted during routine walk-through',
                'root_cause' => 'Procedure not clearly communicated to new crew.',
                'root_cause_incharge' => 'Chief Officer',
                'corrective_action' => 'Re-brief all deck crew and post updated checklist.',
                'corrective_action_incharge' => 'Chief Officer',
                'corrective_action_date' => '2026-07-20',
                'verification' => 'FOLLOW-UP',
                'verification_followup' => 'Confirm checklist posted and crew sign-off collected.',
                'verification_dpa' => 'M. Santos',
                'verification_date' => '2026-07-18',
                'attach_safety_meeting' => true,
                'attach_photo' => true,
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => null,
            ],
            [
                'ncr_no' => 'NC-3004',
                'date_of_nc' => '2026-07-10',
                'vessel_id' => $coralVoyager->id,
                'vessel_company' => 'VESSEL',
                'department_name' => 'Engine',
                'reported_by' => 'SHORE',
                'reporter_name' => 'T. Cruz',
                'description' => 'Bridge equipment calibration due',
                'added_by' => 'SHORE',
                'source_of_nc' => 'OPERATIONAL',
                'is_published' => true,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => null,
            ],
            [
                'ncr_no' => 'NC-3005',
                'date_of_nc' => '2026-07-12',
                'vessel_id' => null,
                'company' => 'BTSolve Shipping',
                'vessel_company' => 'COMPANY',
                'department_name' => 'HSE',
                'reported_by' => 'SHORE',
                'reporter_name' => 'A. Lim',
                'description' => 'Company-wide safety briefing gap',
                'added_by' => 'SHORE',
                'source_of_nc' => 'OPERATIONAL',
                'close_out_completed' => true,
                'close_out_dpa' => 'A. Lim',
                'is_published' => true,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => '2026-07-13',
            ],

            // --- Should NOT appear ---
            [
                'ncr_no' => 'NC-3006',
                'date_of_nc' => '2026-05-01',
                'vessel_id' => $pacificStar->id,
                'vessel_company' => 'VESSEL',
                'description' => 'Closed and approved — should be excluded',
                'added_by' => 'VESSEL',
                'source_of_nc' => '',
                'is_published' => false,
                'is_approved' => true,
                'is_inactive' => false,
                'close_out_date' => '2026-05-10',
            ],
            [
                'ncr_no' => 'NC-3007',
                'date_of_nc' => '2026-05-15',
                'vessel_id' => $coralVoyager->id,
                'vessel_company' => 'VESSEL',
                'description' => 'Flag State item, closed — excluded via source exception',
                'added_by' => 'VESSEL',
                'source_of_nc' => 'FLAG STATE',
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => '2026-05-20',
            ],
            [
                'ncr_no' => 'NC-3008',
                'date_of_nc' => '2026-05-18',
                'vessel_id' => $pacificStar->id,
                'vessel_company' => 'VESSEL',
                'description' => 'Unpublished but already closed — should be excluded',
                'added_by' => 'SHORE',
                'source_of_nc' => '',
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => false,
                'close_out_date' => '2026-05-22',
            ],
            [
                'ncr_no' => 'NC-3009',
                'date_of_nc' => '2026-07-14',
                'vessel_id' => $coralVoyager->id,
                'vessel_company' => 'VESSEL',
                'description' => 'Soft-deleted item — excluded via is_inactive',
                'added_by' => 'VESSEL',
                'source_of_nc' => '',
                'is_published' => false,
                'is_approved' => false,
                'is_inactive' => true,
                'close_out_date' => null,
            ],
        ];

        foreach ($rows as $row) {
            Nonconformity::updateOrCreate(['ncr_no' => $row['ncr_no']], $row);
        }
    }
}
