<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Enums\ReadingSource;
use App\Exceptions\MeterChangeRejected;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a meter by a new one of the same type.
 *
 * Final reading, removal of the old meter and the new meter are stored
 * together or not at all.
 */
class MeterExchange
{
    public function __construct(private readonly MeterMaintenance $maintenance) {}

    /**
     * @param  int|null  $finalValueMilli  May only be omitted if the old meter already has a reading on the exchange date.
     *
     * @throws MeterChangeRejected
     */
    public function exchange(
        Meter $old,
        Carbon $date,
        ?int $finalValueMilli,
        MeterType $newType,
        string $newSerialNumber,
        int $newInitialValueMilli,
        ?User $user = null,
    ): Meter {
        return DB::transaction(function () use ($old, $date, $finalValueMilli, $newType, $newSerialNumber, $newInitialValueMilli, $user) {
            if ($newType !== $old->type) {
                throw new MeterChangeRejected('type', [sprintf(
                    'Der neue Zähler muss dieselbe Art haben wie der ersetzte (%s).', $old->type->label(),
                )]);
            }

            $old = $this->maintenance->retire($old, $date, $finalValueMilli, ReadingSource::Exchange, $user);

            return $old->plot->meters()->create([
                'type' => $newType,
                'serial_number' => $newSerialNumber,
                'installed_on' => $date,
                'initial_value_milli' => $newInitialValueMilli,
            ]);
        });
    }
}
