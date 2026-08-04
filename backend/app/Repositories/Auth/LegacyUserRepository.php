<?php

namespace App\Repositories\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Read-only lookup against the real legacy CI-ISMS staging database
 * (connection 'legacy' — never the default, never migrated). Used only
 * as a login fallback: if a username isn't found locally, we check
 * whether it's a real legacy account before giving up.
 */
class LegacyUserRepository
{
    public function findActiveByUsername(string $username): ?object
    {
        return DB::connection('legacy')->table('tb_users')
            ->where('username', $username)
            ->where('status', 1)
            ->first();
    }
}
