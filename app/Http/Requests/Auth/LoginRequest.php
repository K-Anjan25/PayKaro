<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email + password sign-in, with the pieces the hand-rolled router was missing:
 * rate limiting, a clear message for Google-only accounts, and a transparent
 * hash upgrade for users carried over from the flat-PHP app (which hashed at
 * PHP's default cost, not the workspace's).
 */
class LoginRequest extends FormRequest
{
    /**
     * Attempts allowed per email + IP before the throttle bites.
     */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = $this->findUser();

        // A Google-only account has no password to verify: refuse it in words
        // instead of handing a null hash to the hasher.
        if ($user === null || ! $user->hasPassword()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $user === null
                    ? Lang::get('auth.failed')
                    : 'This account signs in with Google. Use the Google button, or set a password.',
            ]);
        }

        if (! Hash::check((string) $this->input('password'), (string) $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => Lang::get('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user, $this->boolean('remember'));

        if (Hash::needsRehash((string) $user->password)) {
            $user->forceFill(['password' => (string) $this->input('password')])->save();
        }
    }

    protected function findUser(): ?User
    {
        return User::query()->where('email', (string) $this->input('email'))->first();
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => Lang::get('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
