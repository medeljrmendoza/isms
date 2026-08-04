<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Auth\LegacyUserRepository;
use App\Repositories\Auth\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Matches the legacy app's thresholds: 10 attempts / 15 minutes per IP,
     * 5 attempts / 15 minutes per username.
     */
    private const MAX_ATTEMPTS_PER_IP = 10;

    private const MAX_ATTEMPTS_PER_USERNAME = 5;

    private const DECAY_SECONDS = 15 * 60;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LegacyUserRepository $legacyUsers,
    ) {}

    /**
     * Authenticate a username/password pair against the given request.
     * Throws a ValidationException (422 for bad credentials, 429 for
     * lockout) on any failure — the controller does not need to branch.
     */
    public function attemptLogin(string $username, string $password, Request $request): User
    {
        $ipKey = $this->ipRateLimitKey($request);
        $usernameKey = $this->usernameRateLimitKey($username);

        $this->ensureIsNotRateLimited($ipKey, self::MAX_ATTEMPTS_PER_IP);
        $this->ensureIsNotRateLimited($usernameKey, self::MAX_ATTEMPTS_PER_USERNAME);

        $user = $this->users->findActiveByUsername($username);

        if ($user && $this->verifyPassword($user, $password)) {
            return $this->completeLogin($user, $ipKey, $usernameKey, $request);
        }

        $legacyUser = $this->legacyUsers->findActiveByUsername($username);

        if (! $legacyUser || ! Hash::check($password, $legacyUser->password)) {
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            RateLimiter::hit($usernameKey, self::DECAY_SECONDS);

            throw $this->invalidCredentialsException();
        }

        return $this->completeLogin($this->users->syncFromLegacy($legacyUser), $ipKey, $usernameKey, $request);
    }

    private function completeLogin(User $user, string $ipKey, string $usernameKey, Request $request): User
    {
        RateLimiter::clear($ipKey);
        RateLimiter::clear($usernameKey);

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Bcrypt first; fall back to the legacy unsalted SHA1 hash used by the
     * CodeIgniter app, transparently upgrading to bcrypt on success so the
     * fallback path is only ever exercised once per user.
     */
    private function verifyPassword(User $user, string $plainPassword): bool
    {
        if (Hash::check($plainPassword, $user->password)) {
            return true;
        }

        if (hash_equals($user->password, sha1($plainPassword))) {
            $this->users->updatePassword($user, $plainPassword);

            return true;
        }

        return false;
    }

    private function ensureIsNotRateLimited(string $key, int $maxAttempts): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'username' => ['Too many login attempts. Please try again in '.ceil($seconds / 60).' minute(s).'],
        ])->status(429);
    }

    private function invalidCredentialsException(): ValidationException
    {
        return ValidationException::withMessages([
            'username' => ['Username/Password is invalid.'],
        ]);
    }

    private function ipRateLimitKey(Request $request): string
    {
        return 'login-ip:'.$request->ip();
    }

    private function usernameRateLimitKey(string $username): string
    {
        return 'login-username:'.mb_strtolower($username);
    }
}
