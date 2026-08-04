<?php

namespace App\Support;

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
}
