<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();

        if (! $admin) {
            $this->command?->warn('TaskSeeder: no "admin" user found, skipping.');

            return;
        }

        $rows = [
            // --- Should appear ---
            [
                'task_no' => '2026-S2S-00001',
                'category' => 'Safety Manual Review',
                'reference_tag' => 'SMS-REV-14',
                'due_date' => '2026-08-01',
                'priority' => 'HIGH',
                'task_status' => 'NEW',
                'task_type' => 'SHORE TO SHORE',
                'created_by' => $admin->id,
                'is_approved' => false,
                'is_deleted' => false,
            ],
            [
                'task_no' => '2026-S2V-PST-00001',
                'category' => 'PMS Follow-up',
                'reference_tag' => 'PMS-2201',
                'due_date' => '2026-07-30',
                'priority' => 'MEDIUM',
                'task_status' => 'NEW',
                'task_type' => 'SHORE TO VESSEL',
                'created_by' => $admin->id,
                'is_approved' => false,
                'is_deleted' => false,
            ],

            // --- Should NOT appear ---
            [
                'task_no' => '2026-S2S-00002',
                'category' => 'Approved already — excluded',
                'reference_tag' => null,
                'due_date' => '2026-06-01',
                'priority' => 'LOW',
                'task_status' => 'APPROVED',
                'task_type' => 'SHORE TO SHORE',
                'created_by' => $admin->id,
                'is_approved' => true,
                'is_deleted' => false,
            ],
            [
                'task_no' => '2026-S2V-CRV-00001',
                'category' => 'Deleted — excluded',
                'reference_tag' => null,
                'due_date' => '2026-06-15',
                'priority' => 'LOW',
                'task_status' => 'DELETED',
                'task_type' => 'SHORE TO VESSEL',
                'created_by' => $admin->id,
                'is_approved' => false,
                'is_deleted' => true,
            ],
            [
                'task_no' => '2026-V2S-00001',
                'category' => 'Wrong task type — excluded',
                'reference_tag' => null,
                'due_date' => '2026-07-20',
                'priority' => 'LOW',
                'task_status' => 'NEW',
                'task_type' => 'VESSEL TO SHORE',
                'created_by' => $admin->id,
                'is_approved' => false,
                'is_deleted' => false,
            ],
        ];

        foreach ($rows as $row) {
            Task::updateOrCreate(['task_no' => $row['task_no']], $row);
        }
    }
}
