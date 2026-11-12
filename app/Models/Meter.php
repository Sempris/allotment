<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MeterType $type
 * @property Carbon $installed_on
 * @property Carbon|null $removed_on
 * @property int $initial_value_milli
 */
class Meter extends Model
{
    use HasFactory;

    protected $fillable = ['plot_id', 'type', 'serial_number', 'installed_on', 'removed_on', 'initial_value_milli'];

    protected function casts(): array
    {
        return [
            'type' => MeterType::class,
            'installed_on' => 'date',
            'removed_on' => 'date',
            'initial_value_milli' => 'integer',
        ];
    }

    /** @return BelongsTo<Plot, $this> */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /** @return HasMany<Reading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Readings that count: corrected originals are excluded.
     *
     * @return HasMany<Reading, $this>
     */
    public function effectiveReadings(): HasMany
    {
        return $this->readings()->whereNull('superseded_by_id')->orderBy('read_on');
    }

    public function isActiveOn(Carbon $date): bool
    {
        return $date->gte($this->installed_on)
            && ($this->removed_on === null || $date->lte($this->removed_on));
    }

    /** @param Builder<Meter> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_on');
    }
}
