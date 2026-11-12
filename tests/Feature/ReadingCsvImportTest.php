<?php

namespace Tests\Feature;

use App\Enums\ReadingSource;
use App\Models\Meter;
use App\Models\Plot;
use App\Models\User;
use App\Services\ReadingCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ReadingCsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-12');
    }

    public function test_valid_rows_are_imported_and_invalid_rows_are_reported(): void
    {
        $plot = Plot::factory()->create(['number' => '12']);
        Meter::factory()->for($plot)->create(['serial_number' => 'W-1012', 'installed_on' => '2026-04-01']);
        Meter::factory()->for($plot)->electricity()->create(['serial_number' => 'S-2012', 'installed_on' => '2026-04-01']);
        Meter::factory()->create(['serial_number' => 'W-9999']); // belongs to another plot

        $csv = "\u{FEFF}parzelle;zaehlernummer;datum;stand\n"
            ."12;W-1012;15.10.2026;38,250\n"     // fine
            ."12;S-2012;2026-10-15;9999\n"       // above the absolute limit -> flagged
            ."12;W-0000;15.10.2026;10\n"         // unknown meter
            ."12;W-9999;15.10.2026;10\n"         // wrong plot
            ."12;W-1012;32.10.2026;40\n"         // invalid date
            ."\n";

        $results = app(ReadingCsvImporter::class)->import($csv);

        $this->assertSame(['imported', 'flagged', 'rejected', 'rejected', 'rejected'], array_column($results, 'status'));
        $this->assertSame([2, 3, 4, 5, 6], array_column($results, 'line'));
        $this->assertStringContainsString('nicht bekannt', $results[2]['message']);
        $this->assertStringContainsString('gehört zu Parzelle', $results[3]['message']);
        $this->assertStringContainsString('Ungültiges Datum', $results[4]['message']);

        $imported = Meter::where('serial_number', 'W-1012')->first()->readings()->sole();
        $this->assertSame(ReadingSource::Import, $imported->source);
        $this->assertSame(38_250, $imported->value_milli);
    }

    public function test_a_file_with_the_wrong_header_is_refused_as_a_whole(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ReadingCsvImporter::class)->import("parcel,meter,date,value\n1,W-1,15.10.2026,10\n");
    }

    public function test_the_upload_page_shows_the_result_per_row(): void
    {
        $plot = Plot::factory()->create(['number' => '3']);
        Meter::factory()->for($plot)->create(['serial_number' => 'W-1003']);

        $file = UploadedFile::fake()->createWithContent('ablesung.csv', "parzelle;zaehlernummer;datum;stand\n3;W-1003;15.10.2026;12,5\n");

        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->post(route('import.store'), ['file' => $file])
            ->assertOk()
            ->assertSee('1 importiert');
    }
}
