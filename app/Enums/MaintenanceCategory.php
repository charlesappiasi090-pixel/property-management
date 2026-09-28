<?php

namespace App\Enums;

/**
 * The complete catalogue of maintenance categories in PropertyHub.
 *
 * WHY AN ENUM RATHER THAN AD‑HOC STRINGS
 * -------------------------------------
 * `$request->category` compiles to a plain string at the call site, so a
 * typo silently evaluates to "other" and nobody finds out until a report
 * is generated. Declaring the catalogue as a backed enum means the compiler
 * rejects `$request->category = 'plumbung'` and the seeder is the single
 * source of truth for what exists in the database.
 *
 * CATEGORY DEFINITIONS (ordered by typical urgency)
 * ----------------------------------------------
 * `plumbing`   – leaking faucet, clogged drain, water heater issue, etc.
 * `electrical` – power outage, faulty switch, exposed wiring, etc.
 * `hvac`       – heating/cooling system failure, thermostat issue, filter change, etc.
 * `cosmetic`   – paint touch‑up, broken tile, carpet stain, etc.
 * `structural` – roof leak, foundation crack, wall instability, etc.
 * `other`      – anything that does not fit the above vocabularies.
 */
enum MaintenanceCategory: string
{
    case PLUMBING = 'plumbing';
    case ELECTRICAL = 'electrical';
    case HVAC = 'hvac';
    case COSMETIC = 'cosmetic';
    case STRUCTURAL = 'structural';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PLUMBING => 'Plumbing',
            self::ELECTRICAL => 'Electrical',
            self::HVAC => 'HVAC',
            self::COSMETIC => 'Cosmetic',
            self::STRUCTURAL => 'Structural',
            self::OTHER => 'Other',
        };
    }
}