<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Backed directly by the legacy CI-ISMS staging database's tb_users
 * table — there is no local shadow copy. `userID` (a string) is the
 * primary key, aliased as `id` for the rest of the app (UserResource,
 * every controller's `$request->user()?->legacy_user_id` scoping call)
 * since that's what the legacy user identifier always was anyway.
 */
#[Hidden(['password', 'reset_token'])]
class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'legacy';

    protected $table = 'tb_users';

    protected $primaryKey = 'userID';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
            'force_password_change' => 'boolean',
        ];
    }

    /** Legacy has no separate `name` column — first/last, falling back to username. */
    public function getNameAttribute(): string
    {
        $name = trim("{$this->attributes['first_name']} {$this->attributes['last_name']}");

        return $name !== '' ? $name : $this->attributes['username'];
    }

    /** `id` isn't a real column here — userID (the primary key) fills that role everywhere else in the app. */
    public function getIdAttribute(): string
    {
        return $this->attributes['userID'];
    }

    /** Compatibility accessor: every controller scopes legacy queries via `$request->user()?->legacy_user_id`, which is now just the user's own id. */
    public function getLegacyUserIdAttribute(): string
    {
        return $this->attributes['userID'];
    }

    /**
     * Scope to only active (non-disabled) accounts, mirroring the legacy
     * `status = '1'` check that gated login in the CodeIgniter app.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
