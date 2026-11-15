<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $year
 * @property Carbon $key_date
 */
class BillingPeriod extends Model
{
    protected $fillable = ['year', 'key_date'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'key_date' => 'date',
        ];
    }

    /**
     * Key dates of all years that are billed to a key date.
     *
     * @return array<int, Carbon> year => key date
     */
    public static function keyDates(): array
    {
        return self::query()->get()->mapWithKeys(fn (self $p) => [$p->year => $p->key_date])->all();
    }
}
