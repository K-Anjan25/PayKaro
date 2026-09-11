<?php

namespace App\Models;

use App\Enums\AuthProvider;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person in exactly one business.
 *
 * Two things are load-bearing here:
 *  - `business_id` is the tenant the whole request is scoped to;
 *  - `password` is nullable, because a Google-only account has no password to
 *    verify. The login flow refuses those explicitly rather than letting a
 *    null hash through the hasher.
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'role',
        'provider',
        'google_id',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'password' => 'hashed',
            'role' => UserRole::class,
            'provider' => AuthProvider::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Whether this account can be signed into with the email + password form.
     */
    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function initials(): string
    {
        return strtoupper(mb_substr(trim($this->name) !== '' ? trim($this->name)[0] : 'P', 0, 1));
    }

    /**
     * The tenant this user's requests act on.
     */
    public function businessId(): int
    {
        return (int) $this->business_id;
    }
}
