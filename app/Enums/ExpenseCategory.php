<?php

namespace App\Enums;

/**
 * The complete catalogue of expense categories in PropertyHub.
 *
 * WHY AN ENUM RATHER THAN AD‑HOC STRINGS
 * -------------------------------------
 * `$expense->category` compiles to a plain string at the call site, so a
 * typo silently evaluates to "other" and nobody finds out until a report
 * is generated. Declaring the catalogue as a backed enum means the compiler
 * rejects `$expense->category = 'expenss'` and the seeder is the single
 * source of truth for what exists in the database.
 *
 * CATEGORY DEFINITIONS (ordered by typical usage)
 * --------------------------------------------
 * `maintenance`   – plumbing, electrical, HVAC, pest control, etc.
 * `repairs`       – broken window, leaky faucet, paint touch‑up, etc.
 * `utilities`     – electricity, gas, water, internet, trash pickup, etc.
 * `office_supplies` – paper, toner, pens, postage, etc.
 * `insurance`     – property insurance premiums, liability coverage, etc.
 * `other`         – anything that does not fit the above vocabularies.
 */
enum ExpenseCategory: string
{
    case MAINTENANCE = 'maintenance';
    case REPAIRS = 'repairs';
    case UTILITIES = 'utilities';
    case OFFICE_SUPPLIES = 'office_supplies';
    case INSURANCE = 'insurance';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MAINTENANCE => 'Maintenance',
            self::REPAIRS => 'Repairs',
            self::UTILITIES => 'Utilities',
            self::OFFICE_SUPPLIES => 'Office supplies',
            self::INSURANCE => 'Insurance',
            self::OTHER => 'Other',
        };
    }
}