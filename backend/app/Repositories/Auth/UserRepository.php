<?php

namespace App\Repositories\Auth;

use App\Models\User;

class UserRepository
{
    /**
     * Find an active user by username. Returns null if no active account
     * matches, without revealing whether the username exists at all.
     */
    public function findActiveByUsername(string $username): ?User
    {
        return User::query()
            ->active()
            ->where('username', $username)
            ->first();
    }

    /**
     * Persist a rehashed password. Assigning the plain string is enough —
     * the `password` cast on the User model hashes it on save.
     */
    public function updatePassword(User $user, string $plainPassword): void
    {
        $user->password = $plainPassword;
        $user->save();
    }

    /**
     * Mirrors a verified legacy tb_users row into the local `users` table
     * so the rest of the Sanctum/session auth flow (Auth::login(), the
     * `web` guard's user provider) works unchanged. The legacy password
     * is already a bcrypt hash ($2y$...), so assigning it directly is
     * safe — the `hashed` cast on User detects an already-hashed value
     * and skips re-hashing it.
     */
    public function syncFromLegacy(object $legacyUser): User
    {
        $name = trim("{$legacyUser->first_name} {$legacyUser->last_name}") ?: $legacyUser->username;
        $email = $legacyUser->email !== '' ? $legacyUser->email : "{$legacyUser->username}@legacy.local";

        return User::query()->updateOrCreate(
            ['username' => $legacyUser->username],
            [
                'name' => $name,
                'email' => $email,
                'password' => $legacyUser->password,
                'status' => (bool) $legacyUser->status,
                'force_password_change' => (bool) $legacyUser->force_password_change,
                'legacy_user_id' => $legacyUser->userID,
            ],
        );
    }
}
