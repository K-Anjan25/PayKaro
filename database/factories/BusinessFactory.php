<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Pvt Ltd',
            'gstin' => null,
            'pan' => null,
            'udyam_no' => null,
            'bank_name' => 'HDFC Bank',
            'bank_acc_no' => '5010'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999999), 9, '0', STR_PAD_LEFT),
            'bank_ifsc' => 'HDFC0'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'treds_registered' => false,
        ];
    }

    /**
     * A registered MSME with every identity field a claim would cite.
     */
    public function registered(): static
    {
        return $this->state(fn () => [
            'gstin' => '36AAACS'.str_pad((string) fake()->unique()->numberBetween(1000, 9999), 4, '0', false).'F1Z5',
            'pan' => 'AAACS'.fake()->unique()->numberBetween(1000, 9999).'F',
            'udyam_no' => 'UDYAM-TS-12-'.str_pad((string) fake()->unique()->numberBetween(1000000, 9999999), 7, '0', false),
            'treds_registered' => true,
        ]);
    }
}
