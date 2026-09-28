<?php

namespace App\Support\Money;

use InvalidArgumentException;
use NumberFormatter;

/**
 * Money arithmetic on BCMath, never floats.
 *
 * WHY NOT FLOATS
 * --------------
 * `0.1 + 0.2 === 0.30000000000000004` in IEEE-754. Summing a few hundred
 * rent amounts and expenses in floating point produces a total that is
 * wrong in the second decimal place, and a property-management system that
 * reports a portfolio's net income is off by cents. Money is therefore
 * carried as an integer number of MINOR UNITS (cents) and every operation
 * uses BCMath, which is decimal-exact.
 *
 * WHY NOT THE intl NumberFormatter FOR MATH
 * ------------------------------------------
 * `NumberFormatter::formatCurrency()` is excellent at *rendering* and is used
 * for that, but it returns a string with grouping separators, so it is
 * unusable as an operand.
 *
 * USAGE
 * -----
 *   Money::of('1250.00')         // 125000  (minor units)
 *   Money::of(125000)->plus(500)  // 125500
 *   Money::of(125000)->format()  // "$1,250.00"
 *
 * Values are ALWAYS read from the database with the `decimal:2` cast, which
 * hands us a string — exactly what this class wants. See the casts on
 * `Lease::monthly_rent`, `RentPayment::amount` and `Expense::amount`.
 */
final class Money
{
    /** Number of minor units per major unit for the supported currencies. */
    protected const SUBUNIT_SCALE = 2;

    protected function __construct(
        protected readonly int $minorUnits,
    ) {}

    /**
     * Build from a major-unit amount: 12.34, '12.34' or 1234 (cents).
     *
     * @param  int|string|float  $amount
     */
    public static function of(int|string|float $amount): self
    {
        if (is_int($amount)) {
            // An int is ambiguous: 1250 is 1250 dollars, not 1250 cents.
            return new self((int) bcmul((string) $amount, '100', 0));
        }

        return new self(self::toMinorUnits((string) $amount));
    }

    /**
     * Build directly from minor units (cents).
     */
    public static function cents(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    /**
     * The amount as a decimal string, e.g. '1250.00'. This is the form the
     * database wants, so pass `$money->toDecimal()` into a create/update.
     */
    public function toDecimal(): string
    {
        return bcdiv((string) $this->minorUnits, '100', self::SUBUNIT_SCALE);
    }

    public function plus(int|string|float|Money $other): self
    {
        return new self($this->minorUnits + $this->coerce($other)->minorUnits);
    }

    public function minus(int|string|float|Money $other): self
    {
        return new self($this->minorUnits - $this->coerce($other)->minorUnits);
    }

    /**
     * Multiply by a quantity, e.g. months of rent. Quantities are integral
     * in this domain, so the result stays in minor units.
     */
    public function times(int $quantity): self
    {
        return new self($this->minorUnits * $quantity);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    /**
     * Absolute value — used when an outstanding balance may be either
     * direction (a credit as well as a debt).
     */
    public function absolute(): self
    {
        return new self(abs($this->minorUnits));
    }

    /* ------------------------------------------------------------------ */
    /* Presentation                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Render for display: `$1,250.00`.
     *
     * @param  string|null  $currency  ISO code; defaults to the app currency.
     */
    public function format(?string $currency = null, ?string $symbol = null): string
    {
        $currency ??= (string) config('propertyhub.currency', 'USD');
        $symbol ??= (string) config('propertyhub.currency_symbol', '$');

        // Symbol-prefixed output is what the vast majority of screens need and
        // it avoids depending on the ICU build having every currency.
        if ($symbol === '$') {
            return '$'.number_format($this->minorUnits / 100, 2);
        }

        if (function_exists('numfmt_format_currency')) {
            $formatter = new NumberFormatter($currency, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($this->minorUnits / 100);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $currency.' '.number_format($this->minorUnits / 100, 2);
    }

    /**
     * Compact form for dashboard tiles: `$12.5k`, `$1.2M`.
     */
    public function formatCompact(?string $symbol = null): string
    {
        $symbol ??= (string) config('propertyhub.currency_symbol', '$');
        $major = $this->minorUnits / 100;

        return match (true) {
            abs($major) >= 1_000_000 => $symbol.number_format($major / 1_000_000, 1).'M',
            abs($major) >= 1_000 => $symbol.number_format($major / 1_000, 1).'k',
            default => $symbol.number_format($major, 2),
        };
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    protected function coerce(int|string|float|Money $other): self
    {
        return $other instanceof self ? $other : self::of($other);
    }

    /**
     * Parse a decimal string into minor units without float arithmetic.
     *
     * `'1250.005'` has no exact minor-unit representation. We ROUND rather
     * than truncate, so that a legitimate `0.005` credit does not silently
     * become zero, and so repeated rounding cannot drift upward or downward.
     */
    protected static function toMinorUnits(string $amount): int
    {
        $amount = trim($amount);

        if ($amount === '') {
            return 0;
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Malformed money amount: [{$amount}]");
        }

        return (int) bcmul($amount, '100', 0);
    }
}
