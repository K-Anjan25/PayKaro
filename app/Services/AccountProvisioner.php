<?php

namespace App\Services;

use App\Enums\AuthProvider;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creating a workspace (business + owner), whether from a sign-up form or a
 * Google assertion.
 *
 * Both paths must end with exactly one business per user, so the logic lives
 * here instead of in each controller — a sign-up and a first Google login can
 * never set up a workspace differently.
 */
class AccountProvisioner
{
    /**
     * Sign-up: a new business and its owner, atomically.
     *
     * @param  array{name: string, email: string, password: ?string, business_name?: string|null}  $data
     */
    public function register(array $data): User
    {
        $name = trim($data['name']);
        $businessName = trim($data['business_name'] ?? '') ?: $name;

        return DB::transaction(function () use ($data, $name, $businessName) {
            $business = Business::create([
                'name' => $businessName,
                'treds_registered' => false,
            ]);

            return $business->users()->create([
                'name' => $name !== '' ? $name : 'Owner',
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'] ?? null,
                'role' => UserRole::Owner,
                'provider' => AuthProvider::Email,
            ]);
        });
    }

    /**
     * Google Sign-In: match, link, or provision.
     *
     * The order matters. An existing account for the same email is *linked*
     * rather than duplicated — a supplier who signed up with a password must not
     * get a second, empty business when they click "Continue with Google".
     *
     * @param  array{id: string, email: string, name: string, avatar: string}  $profile
     */
    public function findOrCreateFromGoogle(array $profile): User
    {
        $googleId = trim($profile['id']);
        $email = strtolower(trim($profile['email']));

        if ($googleId === '' || $email === '') {
            throw new RuntimeException('Google profile is missing its subject id or email.');
        }

        if ($existing = User::query()->where('google_id', $googleId)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($googleId, $email, $profile) {
            $name = trim($profile['name']) ?: Str::before($email, '@');
            $avatar = $profile['avatar'] ?? '';

            if ($linked = User::query()->where('email', $email)->first()) {
                $linked->forceFill([
                    'provider' => AuthProvider::Google,
                    'google_id' => $googleId,
                    'avatar_url' => $linked->avatar_url ?: $avatar,
                ])->save();

                return $linked->refresh();
            }

            $business = Business::create(['name' => $name !== '' ? $name : 'My Business']);

            return $business->users()->create([
                'name' => $name !== '' ? $name : 'Owner',
                'email' => $email,
                // No password: this account signs in with Google only, and the
                // password form must refuse it rather than "create" one.
                'password' => null,
                'role' => UserRole::Owner,
                'provider' => AuthProvider::Google,
                'google_id' => $googleId,
                'avatar_url' => $avatar ?: null,
            ]);
        });
    }
}
