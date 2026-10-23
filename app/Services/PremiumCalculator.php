<?php

namespace App\Services;

use App\Enums\PolicyState;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use InvalidArgumentException;

class PremiumCalculator
{
    public const BASE_RATES = [
        'BASIC' => '12',
        'PLUS' => '20',
        'PREMIUM' => '30',
    ];

    public const AGE_MULTIPLIERS = [
        ['min' => 0, 'max' => 17, 'value' => '1.10'],
        ['min' => 18, 'max' => 60, 'value' => '1.00'],
        ['min' => 61, 'max' => 70, 'value' => '1.50'],
        ['min' => 71, 'max' => null, 'value' => '2.00'],
    ];

    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => '1.00',
        'INTERNATIONAL' => '1.80',
    ];

    public const COVERAGE_MULTIPLIERS = [
        100000 => '1.00',
        300000 => '1.25',
        500000 => '1.50',
    ];

    public static function calculate(string $dob, string $tripStart, string $tripEnd, string $planCode, string $destination, int $sumInsured): array
    {
        $dobCarbon = Carbon::parse($dob);
        $tripStartCarbon = Carbon::parse($tripStart);
        $tripEndCarbon = Carbon::parse($tripEnd);

        if ($tripEndCarbon->lt($tripStartCarbon)) {
            throw new InvalidArgumentException('Trip end must be greater than or equal to trip start.');
        }

        $travelDays = max(1, $tripStartCarbon->diffInDays($tripEndCarbon) + 1);

        $baseRate = static::getBaseRate($planCode);
        $ageMultiplier = static::getAgeMultiplier($dobCarbon, $tripStartCarbon);
        $destinationMultiplier = static::getDestinationMultiplier($destination);
        $coverageMultiplier = static::getCoverageMultiplier($sumInsured);

        $premium = BigDecimal::of($baseRate)
            ->multipliedBy(BigDecimal::of((string) $travelDays))
            ->multipliedBy(BigDecimal::of($ageMultiplier))
            ->multipliedBy(BigDecimal::of($destinationMultiplier))
            ->multipliedBy(BigDecimal::of($coverageMultiplier))
            ->toScale(2, RoundingMode::HALF_UP);

        return [
            'premium' => $premium->toScale(2, RoundingMode::HALF_UP)->toString(),
            'travel_days' => $travelDays,
            'base_rate' => $baseRate,
            'age_multiplier' => $ageMultiplier,
            'destination_multiplier' => $destinationMultiplier,
            'coverage_multiplier' => $coverageMultiplier,
        ];
    }

    public static function getAgeOnTripStart(string $dob, string $tripStart): int
    {
        return Carbon::parse($dob)->diffInYears(Carbon::parse($tripStart));
    }

    public static function getBaseRate(string $planCode): string
    {
        if (! array_key_exists($planCode, self::BASE_RATES)) {
            throw new InvalidArgumentException('Unsupported plan code provided.');
        }

        return self::BASE_RATES[$planCode];
    }

    public static function getAgeMultiplier(Carbon $dob, Carbon $tripStart): string
    {
        $age = $dob->diffInYears($tripStart);

        foreach (self::AGE_MULTIPLIERS as $range) {
            $min = $range['min'];
            $max = $range['max'];

            if ($age >= $min && ($max === null || $age <= $max)) {
                return $range['value'];
            }
        }

        throw new InvalidArgumentException('Unable to determine age multiplier.');
    }

    public static function getDestinationMultiplier(string $destination): string
    {
        if (! array_key_exists($destination, self::DESTINATION_MULTIPLIERS)) {
            throw new InvalidArgumentException('Unsupported destination.');
        }

        return self::DESTINATION_MULTIPLIERS[$destination];
    }

    public static function getCoverageMultiplier(int $sumInsured): string
    {
        if (! array_key_exists($sumInsured, self::COVERAGE_MULTIPLIERS)) {
            throw new InvalidArgumentException('Unsupported coverage amount.');
        }

        return self::COVERAGE_MULTIPLIERS[$sumInsured];
    }
}
