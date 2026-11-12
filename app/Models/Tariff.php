<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $year
 * @property MeterType $meter_type
 * @property int $price_per_unit_cents
 * @property int $base_fee_cents
 */
class Tariff extends Model
{
    protected $fillable = ['year', 'meter_type', 'price_per_unit_cents', 'base_fee_cents'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'meter_type' => MeterType::class,
            'price_per_unit_cents' => 'integer',
            'base_fee_cents' => 'integer',
        ];
    }
}
