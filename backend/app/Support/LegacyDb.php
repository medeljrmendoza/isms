<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Helpers shared by the dashlet repositories that can optionally read
 * from the real legacy CI-ISMS staging database (connection 'legacy')
 * instead of local seed data, for demoing genuine cloud connectivity.
 * Read-only — nothing here ever writes to the legacy connection.
 */
class LegacyDb
{
    public static function isConfigured(): bool
    {
        return config('database.connections.legacy.database') !== '';
    }

    /** @return array<string, string> vesID => "PREFIX Name" */
    public static function vesselNames(): array
    {
        return DB::connection('legacy')->table('tb_vessel')
            ->select('vesID', 'vessel_prefix', 'vessel_name')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->vesID => trim("{$v->vessel_prefix} {$v->vessel_name}")])
            ->all();
    }

    /**
     * The vessels a given legacy user is assigned to, via tb_user_vessel —
     * every legacy dashlet scopes its data to this set. Returns empty for
     * a null/unsynced user (e.g. the local dev admin), which correctly
     * mirrors how legacy behaves for a userID with no vessel assignments.
     *
     * @return Collection<int, string>
     */
    public static function assignedVesselIds(?string $legacyUserId): Collection
    {
        if ($legacyUserId === null) {
            return collect();
        }

        return DB::connection('legacy')->table('tb_user_vessel')
            ->where('userID', $legacyUserId)
            ->pluck('vesID');
    }

    /**
     * @return Collection<int, string> vesIDs where vessel_status = 'ACTIVE'
     */
    public static function activeVesselIds(): Collection
    {
        return DB::connection('legacy')->table('tb_vessel')
            ->where('vessel_status', 'ACTIVE')
            ->pluck('vesID');
    }

    /**
     * Ported from Dashboard_pms.php's pms_vessel_query: active vessels
     * belonging to a principal that is also active.
     *
     * @return Collection<int, string>
     */
    public static function activeVesselIdsWithActivePrincipal(): Collection
    {
        return DB::connection('legacy')->table('tb_vessel')
            ->join('tb_principal', 'tb_principal.principalID', '=', 'tb_vessel.principalID')
            ->where('tb_vessel.vessel_status', 'ACTIVE')
            ->where('tb_principal.status', '1')
            ->pluck('tb_vessel.vesID');
    }

    /**
     * The vessel filter dropdown for a module's full list page, scoped
     * to the vessels this legacy user is assigned to — the legacy
     * counterpart of each repository's local `vesselOptions()` (which
     * lists every local Vessel row unscoped).
     *
     * @return array<int, array{id:string,label:string}>
     */
    public static function assignedVesselOptions(?string $legacyUserId): array
    {
        $names = self::vesselNames();

        return self::assignedVesselIds($legacyUserId)
            ->map(fn ($vesID) => ['id' => $vesID, 'label' => $names[$vesID] ?? ''])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * tb_address_book is a shared contacts table — some modules (SIRE,
     * Non-SIRE) store a "company"/"inspector" column as an FK into it
     * rather than free text (unlike e.g. Flag State's plain-text
     * inspector column). Looks up a single row and returns a person
     * label (name, falling back to company) and the row's own company
     * field, for the two different display needs.
     *
     * @return array{name: string, company: string}|null
     */
    public static function addressBookEntry(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        $r = DB::connection('legacy')->table('tb_address_book')->where('id', $id)->first();

        if ($r === null) {
            return null;
        }

        $name = trim("{$r->firstname} {$r->lastname}");

        return [
            'name' => $name !== '' ? $name : $r->company,
            'company' => $r->company,
        ];
    }
}
