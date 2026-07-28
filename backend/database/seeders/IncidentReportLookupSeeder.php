<?php

namespace Database\Seeders;

use App\Models\IncidentLocation;
use App\Models\IncidentOperation;
use App\Models\LocationOfInjury;
use App\Models\RootCause;
use App\Models\RootCauseCategory;
use App\Models\TypeOfInjury;
use Illuminate\Database\Seeder;

class IncidentReportLookupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Head', 'Arm', 'Leg', 'Torso', 'Other'] as $bodyPart) {
            LocationOfInjury::firstOrCreate(['body_part' => $bodyPart]);
        }

        foreach (['Cut/Laceration', 'Fracture', 'Burn', 'Sprain/Strain', 'Other'] as $type) {
            TypeOfInjury::firstOrCreate(['name' => $type]);
        }

        foreach (['Engine Room', 'Deck', 'Galley', 'Cargo Hold', 'Bridge', 'OTHER'] as $location) {
            IncidentLocation::firstOrCreate(['name' => $location]);
        }

        foreach (['Mooring', 'Cargo Operations', 'Bunkering', 'Anchoring', 'Navigation', 'OTHER'] as $operation) {
            IncidentOperation::firstOrCreate(['name' => $operation]);
        }

        $human = RootCauseCategory::firstOrCreate(['name' => 'HUMAN FACTOR']);
        $equipment = RootCauseCategory::firstOrCreate(['name' => 'EQUIPMENT FAILURE']);
        $procedure = RootCauseCategory::firstOrCreate(['name' => 'PROCEDURAL']);
        $other = RootCauseCategory::firstOrCreate(['name' => 'OTHER']);

        foreach (['Lack of Training', 'Fatigue', 'Complacency'] as $name) {
            RootCause::firstOrCreate(['root_cause_category_id' => $human->id, 'name' => $name]);
        }
        foreach (['Equipment Malfunction', 'Inadequate Maintenance'] as $name) {
            RootCause::firstOrCreate(['root_cause_category_id' => $equipment->id, 'name' => $name]);
        }
        foreach (['Procedure Not Followed', 'Procedure Inadequate'] as $name) {
            RootCause::firstOrCreate(['root_cause_category_id' => $procedure->id, 'name' => $name]);
        }
        RootCause::firstOrCreate(['root_cause_category_id' => $other->id, 'name' => 'Other']);
    }
}
