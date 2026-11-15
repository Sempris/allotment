<?php

/*
 | Records one reading in a process of its own and prints "stored" or "rejected".
 | Used by ConcurrentReadingsTest to let two real database sessions race.
 |
 | The plausibility check is slowed down on purpose: without a lock, both
 | processes would finish their check before either of them writes.
 */

use App\Enums\ReadingSource;
use App\Exceptions\ReadingRejected;
use App\Models\Meter;
use App\Services\PlausibilityChecker;
use App\Services\PlausibilityResult;
use App\Services\ReadingRecorder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $meterId, $date, $valueMilli] = $argv;

$app->bind(PlausibilityChecker::class, fn () => new class extends PlausibilityChecker
{
    public function check(Meter $meter, Carbon $readOn, int $valueMilli, ?int $ignoreReadingId = null): PlausibilityResult
    {
        $result = parent::check($meter, $readOn, $valueMilli, $ignoreReadingId);
        usleep(400_000);

        return $result;
    }
});

try {
    $app->make(ReadingRecorder::class)->record(Meter::findOrFail($meterId), Carbon::parse($date), (int) $valueMilli, ReadingSource::Manual);
    echo 'stored';
} catch (ReadingRejected) {
    echo 'rejected';
}
