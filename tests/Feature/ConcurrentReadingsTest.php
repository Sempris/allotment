<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Services\ReadingRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Two people enter readings for the same meter at the same moment, e.g. a
 * CSV import and the form. Each reading is valid on its own, but not both:
 *
 *   A: 01.06.2026 – 150.000 m³      B: 17.10.2026 – 120.000 m³
 *
 * Whoever comes second must be rejected ("lower than the previous value" or
 * "higher than a later reading"). Without a lock, both would pass the check.
 *
 * The test uses two real processes and therefore real commits instead of the
 * usual test transaction. It runs against whatever database the suite uses.
 */
class ConcurrentReadingsTest extends TestCase
{
    private ?string $sqliteFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            // Other processes cannot see an in-memory database.
            $this->sqliteFile = storage_path('framework/testing/concurrency-'.getmypid().'.sqlite');
            touch($this->sqliteFile);
            config(['database.connections.sqlite.database' => $this->sqliteFile]);
            DB::purge();
        }

        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        // Leave an empty, migrated schema for the tests that follow.
        Artisan::call('migrate:fresh');
        DB::disconnect();

        if ($this->sqliteFile !== null) {
            @unlink($this->sqliteFile);
        }

        parent::tearDown();
    }

    /** @return array<string, array{bool}> */
    public static function startOrders(): array
    {
        return ['A starts first' => [true], 'B starts first' => [false]];
    }

    #[DataProvider('startOrders')]
    public function test_of_two_conflicting_parallel_readings_exactly_one_is_stored(bool $aFirst): void
    {
        $meter = Meter::factory()->create(['installed_on' => '2025-01-01']);
        app(ReadingRecorder::class)->record($meter, Carbon::parse('2025-10-17'), 100_000, ReadingSource::Manual);
        DB::disconnect();

        $a = $this->recordInOwnProcess($meter, '2026-06-01', 150_000);
        $b = $this->recordInOwnProcess($meter, '2026-10-17', 120_000);

        foreach ($aFirst ? [$a, $b] : [$b, $a] as $process) {
            $process->start();
            usleep(100_000); // the first process is inside its check when the second one starts
        }

        $a->wait();
        $b->wait();

        $this->assertSame('', $a->getErrorOutput().$b->getErrorOutput());

        $outputs = [$a->getOutput(), $b->getOutput()];
        sort($outputs);
        $this->assertSame(['rejected', 'stored'], $outputs, 'Exactly one of the two readings may be stored, the other must be rejected cleanly.');

        // The one that started first wins; the other one sees it and is rejected.
        $this->assertSame('stored', ($aFirst ? $a : $b)->getOutput());
        $this->assertSame(2, $meter->effectiveReadings()->count());
    }

    private function recordInOwnProcess(Meter $meter, string $date, int $valueMilli): Process
    {
        $connection = config('database.connections.'.config('database.default'));

        return new Process(
            [PHP_BINARY, base_path('tests/Support/record_reading.php'), (string) $meter->id, $date, (string) $valueMilli],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => config('database.default'),
                'DB_HOST' => $connection['host'] ?? '',
                'DB_PORT' => $connection['port'] ?? '',
                'DB_DATABASE' => $connection['database'],
                'DB_USERNAME' => $connection['username'] ?? '',
                'DB_PASSWORD' => $connection['password'] ?? '',
                'DB_URL' => '',
            ],
            timeout: 30,
        );
    }
}
