<?php

namespace App\Models;

use App\Enums\ReadingSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $read_on
 * @property int $value_milli
 * @property ReadingSource $source
 * @property string|null $flag_reason
 * @property Carbon|null $flag_reviewed_at
 */
class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id', 'read_on', 'value_milli', 'source', 'note', 'recorded_by', 'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'value_milli' => 'integer',
            'source' => ReadingSource::class,
            'flag_reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Meter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<Reading, $this> */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Reading::class, 'superseded_by_id');
    }

    /** @return HasOne<Reading, $this> */
    public function correctedReading(): HasOne
    {
        return $this->hasOne(Reading::class, 'superseded_by_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_id !== null;
    }

    public function needsReview(): bool
    {
        return $this->flag_reason !== null && $this->flag_reviewed_at === null;
    }

    /** @param Builder<Reading> $query */
    public function scopeEffective(Builder $query): void
    {
        $query->whereNull('superseded_by_id');
    }

    /** @param Builder<Reading> $query */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->effective()->whereNotNull('flag_reason')->whereNull('flag_reviewed_at');
    }
}
