<?php

namespace App\Enums;

/**
 * How a user proves who they are.
 *
 * A `google` user may have no password at all, which is why users.password is
 * nullable and why the password form refuses them instead of creating one.
 */
enum AuthProvider: string
{
    case Email = 'email';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Google => 'Google',
        };
    }
}
