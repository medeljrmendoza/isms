<?php

namespace Database\Seeders;

use App\Models\Pms\PmsClassification;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsSubClassification;
use App\Models\Principal;
use App\Models\Vessel;
use App\Models\VesselType;
use Illuminate\Database\Seeder;

/**
 * Seeds the Setup > PMS > Configuration/Department/Classifications
 * modules: a principal + vessel types for Configuration's filter and
 * the classification-to-vessel-type links, and a realistic set of
 * classifications/sub-classifications matching legacy's own (MAIN
 * ENGINE, DECK MACHINERY, etc).
 */
class PmsSetupSeeder extends Seeder
{
    public function run(): void
    {
        $principal = Principal::firstOrCreate(['name' => 'Swan Shipping Corp.']);

        $shortNames = ['Pacific Star' => 'PST', 'Coral Voyager' => 'CVY', 'Northern Light' => 'NLT'];
        Vessel::query()->whereNull('principal_id')->each(function (Vessel $vessel) use ($principal, $shortNames) {
            $vessel->update([
                'principal_id' => $principal->id,
                'configuration' => 'VESSEL',
                'short_name' => $shortNames[$vessel->name] ?? strtoupper(substr($vessel->name, 0, 3)),
            ]);
        });

        $lpgCarrier = VesselType::firstOrCreate(['name' => 'LPG Carrier']);
        VesselType::firstOrCreate(['name' => 'Bulk Carrier']);
        VesselType::firstOrCreate(['name' => 'Oil Tanker']);

        $deck = PmsDepartment::firstOrCreate(['name' => 'Deck']);
        $engine = PmsDepartment::firstOrCreate(['name' => 'Engine']);
        $safety = PmsDepartment::firstOrCreate(['name' => 'Safety']);

        $mainEngine = PmsClassification::firstOrCreate(
            ['name' => 'MAIN ENGINE'],
            ['description' => 'Main propulsion engine and associated systems.'],
        );
        $mainEngine->departments()->syncWithoutDetaching([$engine->id]);
        $mainEngine->vesselTypes()->syncWithoutDetaching([$lpgCarrier->id]);
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $mainEngine->id, 'chart_code' => 'ME-01'],
            ['name' => 'CYLINDER HEAD & VALVES', 'description' => 'Cylinder heads, inlet/exhaust valves.'],
        );
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $mainEngine->id, 'chart_code' => 'ME-02'],
            ['name' => 'FUEL INJECTION SYSTEM', 'description' => 'Injectors, fuel pumps, fuel valves.'],
        );
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $mainEngine->id, 'chart_code' => 'ME-03'],
            ['name' => 'TURBOCHARGER', 'description' => null],
        );

        $deckMachinery = PmsClassification::firstOrCreate(
            ['name' => 'DECK MACHINERY'],
            ['description' => 'Mooring winches, windlass, cranes, and other deck equipment.'],
        );
        $deckMachinery->departments()->syncWithoutDetaching([$deck->id]);
        $deckMachinery->vesselTypes()->syncWithoutDetaching([$lpgCarrier->id]);
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $deckMachinery->id, 'chart_code' => 'DM-01'],
            ['name' => 'MOORING WINCHES', 'description' => null],
        );
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $deckMachinery->id, 'chart_code' => 'DM-02'],
            ['name' => 'ANCHOR WINDLASS', 'description' => null],
        );

        $cargoSystem = PmsClassification::firstOrCreate(
            ['name' => 'CARGO SYSTEM'],
            ['description' => 'Cargo pumps, compressors, and containment systems.'],
        );
        $cargoSystem->departments()->syncWithoutDetaching([$engine->id, $deck->id]);
        $cargoSystem->vesselTypes()->syncWithoutDetaching([$lpgCarrier->id]);
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $cargoSystem->id, 'chart_code' => 'CS-01'],
            ['name' => 'CARGO COMPRESSORS', 'description' => null],
        );

        $safetyEquipment = PmsClassification::firstOrCreate(
            ['name' => 'SAFETY EQUIPMENT'],
            ['description' => 'Firefighting, lifesaving, and other safety appliances.', 'is_active' => false],
        );
        $safetyEquipment->departments()->syncWithoutDetaching([$safety->id]);
        PmsSubClassification::firstOrCreate(
            ['pms_classification_id' => $safetyEquipment->id, 'chart_code' => 'SE-01'],
            ['name' => 'FIRE EXTINGUISHERS', 'description' => null, 'is_active' => false],
        );
    }
}
