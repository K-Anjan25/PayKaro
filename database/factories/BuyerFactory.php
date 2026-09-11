<?php

namespace Database\Factories;

use App\Enums\BuyerType;
use App\Enums\TredsOnboarding;
use App\Models\Buyer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Buyer>
 *
 * Note there is no `business_id` here: the tenant scope's creating hook stamps
 * it, which is precisely the behaviour under test.
 */
class BuyerFactory extends Factory
{
    protected $model = Buyer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim(fake()->company(), '.'),
            'gstin' => null,
            'type' => BuyerType::Private,
            'treds_onboarded' => TredsOnboarding::Unknown,
        ];
    }

    /**
     * A CPSE buyer onboarded on TReDS — the invoice-discounting happy path.
     */
    public function tredsReady(): static
    {
        return $this->state(fn () => [
            'type' => BuyerType::Cpse,
            'treds_onboarded' => TredsOnboarding::Yes,
            'gstin' => self::gstin(),
        ]);
    }

    public function notOnboarded(): static
    {
        return $this->state(fn () => [
            'type' => BuyerType::Private,
            'treds_onboarded' => TredsOnboarding::No,
            'gstin' => self::gstin(),
        ]);
    }

    /**
     * A syntactically valid 15-character GSTIN, for tests that post real payloads.
     */
    public static function gstin(): string
    {
        return fake()->numberBetween(10, 99)
            .Str::upper(fake()->lexify('?????'))
            .fake()->numberBetween(1000, 9999)
            .Str::upper(fake()->lexify('?'))
            .fake()->numberBetween(1, 9)
            .'Z'
            .Str::upper(fake()->bothify('?'));
    }
}
