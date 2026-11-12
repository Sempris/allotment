<?php

namespace App\Services;

use App\Enums\ReadingSource;
use App\Exceptions\ReadingRejected;
use App\Models\Meter;
use App\Models\User;
use App\Support\Milli;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Imports the reading list that board members fill in on reading day.
 *
 * Expected columns (semicolon-separated, as exported by German Excel):
 *   parzelle;zaehlernummer;datum;stand
 *
 * Every row is checked on its own. Valid rows are stored, invalid rows are
 * reported with the reason, so one typo does not block the whole list.
 */
class ReadingCsvImporter
{
    private const COLUMNS = ['parzelle', 'zaehlernummer', 'datum', 'stand'];

    public function __construct(private readonly ReadingRecorder $recorder) {}

    /**
     * @return list<array{line: int, status: 'imported'|'flagged'|'rejected', serial: string, message: string|null}>
     */
    public function import(string $csv, ?User $user = null): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($this->stripBom($csv)));
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv((string) array_shift($lines), ';', '"', ''));

        if ($header !== self::COLUMNS) {
            throw new InvalidArgumentException('Die erste Zeile muss lauten: '.implode(';', self::COLUMNS));
        }

        $results = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 2;

            if (trim($line) === '') {
                continue;
            }

            $row = array_map('trim', str_getcsv($line, ';', '"', ''));
            $serial = $row[1] ?? '';

            try {
                if (count($row) !== count(self::COLUMNS)) {
                    throw new ReadingRejected(['Die Zeile hat nicht genau vier Spalten.']);
                }

                [$plotNumber, $serial, $date, $value] = $row;

                $meter = Meter::query()->with('plot')->where('serial_number', $serial)->first()
                    ?? throw new ReadingRejected(["Zähler {$serial} ist nicht bekannt."]);

                if ($meter->plot->number !== $plotNumber) {
                    throw new ReadingRejected(["Zähler {$serial} gehört zu Parzelle {$meter->plot->number}, nicht zu {$plotNumber}."]);
                }

                $reading = $this->recorder->record(
                    $meter, $this->parseDate($date), Milli::parse($value), ReadingSource::Import, $user,
                );

                $results[] = [
                    'line' => $lineNumber,
                    'status' => $reading->flag_reason ? 'flagged' : 'imported',
                    'serial' => $serial,
                    'message' => $reading->flag_reason,
                ];
            } catch (ReadingRejected|InvalidArgumentException $e) {
                $results[] = ['line' => $lineNumber, 'status' => 'rejected', 'serial' => $serial, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function parseDate(string $value): Carbon
    {
        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);

                if ($date !== null && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        throw new InvalidArgumentException("Ungültiges Datum: {$value} (erwartet TT.MM.JJJJ).");
    }

    private function stripBom(string $csv): string
    {
        return str_starts_with($csv, "\u{FEFF}") ? substr($csv, 3) : $csv;
    }
}
