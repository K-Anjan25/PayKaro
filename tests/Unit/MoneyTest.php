<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

/**
 * Rupee formatting, which a statutory claim packet leans on more than anywhere else.
 *
 * The bug this exists for: paise were rendered with `number_format($paise, 0)`, so
 * anything under ten paise lost its leading zero. `₹4,112.02` printed as `₹4,112.2`
 * — a figure wrong by a factor of ten, inside a document a tribunal adds up, on a
 * line that then does not reconcile with the total below it.
 */
final class MoneyTest extends TestCase
{
    public function test_paise_under_ten_keep_their_leading_zero(): void
    {
        $this->assertSame('₹4,112.02', Money::inr(4112.02));
        $this->assertSame('₹0.02', Money::inr(0.02));
        $this->assertSame('₹0.05', Money::inr(0.05));
        $this->assertSame('₹1,00,000.01', Money::inr(100000.01));
    }

    public function test_paise_above_ten_are_unchanged(): void
    {
        $this->assertSame('₹4,112.20', Money::inr(4112.20));
        $this->assertSame('₹4,867.50', Money::inr(4867.50));
        $this->assertSame('₹3,62,979.52', Money::inr(362979.52));
    }

    public function test_grouping_is_indian(): void
    {
        // Lakh and crore, not thousands: ₹12,34,567.89, never ₹1,234,567.89.
        $this->assertSame('₹12,34,567.89', Money::inr(1234567.89));
        $this->assertSame('₹1,23,45,678.09', Money::inr(12345678.09));
        $this->assertSame('₹9,67,600', Money::inr(967600));
    }

    public function test_whole_rupees_gain_no_decimal_point(): void
    {
        $this->assertSame('₹1,000', Money::inr(1000));
        $this->assertSame('₹0', Money::inr(0));
    }

    public function test_a_negative_amount_keeps_its_sign_outside_the_symbol(): void
    {
        $this->assertSame('-₹15.05', Money::inr(-15.05));
        $this->assertSame('-₹1,000', Money::inr(-1000));
    }

    public function test_the_precise_form_never_collapses_to_a_whole_number(): void
    {
        // GST fields print ₹1,00,000.00, and it has to stay ₹1,00,000.00.
        $this->assertSame('₹1,00,000.00', Money::precise(100000));
        $this->assertSame('₹4,112.02', Money::precise(4112.02));
    }

    public function test_an_unparseable_amount_is_zero_rather_than_a_crash(): void
    {
        $this->assertSame('₹0', Money::inr(null));
        $this->assertSame('₹0', Money::inr('not a number'));
    }

    public function test_rounding_happens_once_at_paisa_precision(): void
    {
        // Display must not disagree with the stored balance by a rounding step of its
        // own: 4112.025 is a half-paisa, and it lands the same way twice.
        $this->assertSame(Money::inr(4112.025), Money::inr(4112.025));
        $this->assertSame('₹4,112.03', Money::inr(4112.025));
    }
}
