<?php

namespace App\Repositories;

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
}
