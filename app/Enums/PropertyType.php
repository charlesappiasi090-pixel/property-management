<?php

namespace App\Enums;

/**
 * Classification of a property, stored in `properties.property_type` (Phase 2).
 *
 * Declared in Phase 1 so the schema, factories and filter UI agree on one
 * vocabulary from the start. The backing strings are a data contract — the
 * enum is the only thing allowed to write them.
 */
enum PropertyType: string
{
    case APARTMENT = 'apartment';
    case HOUSE = 'house';
    case TOWNHOUSE = 'townhouse';
    case CONDO = 'condo';
    case DUPLEX = 'duplex';
    case VILLA = 'villa';
    case COMMERCIAL = 'commercial';
    case MIXED_USE = 'mixed_use';
    case LAND = 'land';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::APARTMENT => 'Apartment',
            self::HOUSE => 'House',
            self::TOWNHOUSE => 'Townhouse',
            self::CONDO => 'Condo',
            self::DUPLEX => 'Duplex',
            self::VILLA => 'Villa',
            self::COMMERCIAL => 'Commercial',
            self::MIXED_USE => 'Mixed use',
            self::LAND => 'Land',
            self::OTHER => 'Other',
        };
    }

    /**
     * Commercial and land properties have no residential units, so the unit
     * management screens are hidden for them.
     *
     * This is a PRESENTATION rule. The data layer enforces the same rule
     * independently — `UpdatePropertyRequest` refuses to retype a property
     * that already has units, and `PropertyCatalog::createUnit()` throws
     * `LogicException` — because a rule that only hides a button is a rule that
     * a crafted POST walks straight past.
     */
    public function allowsResidentialUnits(): bool
    {
        return ! in_array($this, [self::COMMERCIAL, self::LAND], strict: true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
