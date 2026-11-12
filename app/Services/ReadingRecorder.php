<?php

namespace App\Services;

use App\Enums\ReadingSource;
use App\Exceptions\ReadingRejected;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place where readings are written. Manual entry, CSV import and
 * corrections all go through here, so they share the same rules.
 */
class ReadingRecorder
{
    public function __construct(private readonly PlausibilityChecker $checker) {}

    /**
     * @throws ReadingRejected
     */
    public function record(
        Meter $meter,
        Carbon $readOn,
        int $valueMilli,
        ReadingSource $source,
        ?User $user = null,
        ?string $note = null,
    ): Reading {
        return DB::transaction(function () use ($meter, $readOn, $valueMilli, $source, $user, $note) {
            // Lock the meter so two parallel entries (e.g. an import and a manual entry)
            // cannot both pass the "not lower than the previous value" check.
            $meter = Meter::query()->lockForUpdate()->findOrFail($meter->getKey());

            $result = $this->checker->check($meter, $readOn, $valueMilli);

            if ($result->isRejected()) {
                throw new ReadingRejected($result->errors);
            }

            return $meter->readings()->create([
                'read_on' => $readOn,
                'value_milli' => $valueMilli,
                'source' => $source,
                'note' => $note,
                'recorded_by' => $user?->getKey(),
                'flag_reason' => $result->warning,
            ]);
        });
    }

    /**
     * Replace a reading without losing it: the original stays in the history,
     * marked as superseded by the new one.
     *
     * @throws ReadingRejected
     */
    public function correct(Reading $original, Carbon $readOn, int $valueMilli, string $note, ?User $user = null): Reading
    {
        return DB::transaction(function () use ($original, $readOn, $valueMilli, $note, $user) {
            $meter = Meter::query()->lockForUpdate()->findOrFail($original->meter_id);
            $original = Reading::query()->lockForUpdate()->findOrFail($original->getKey());

            if ($original->isSuperseded()) {
                throw new ReadingRejected(['Diese Ablesung wurde bereits korrigiert. Bitte die aktuelle Version korrigieren.']);
            }

            $result = $this->checker->check($meter, $readOn, $valueMilli, ignoreReadingId: $original->getKey());

            if ($result->isRejected()) {
                throw new ReadingRejected($result->errors);
            }

            $correction = $meter->readings()->create([
                'read_on' => $readOn,
                'value_milli' => $valueMilli,
                'source' => ReadingSource::Correction,
                'note' => $note,
                'recorded_by' => $user?->getKey(),
                'flag_reason' => $result->warning,
            ]);

            $original->forceFill(['superseded_by_id' => $correction->getKey()])->save();

            return $correction;
        });
    }
}
