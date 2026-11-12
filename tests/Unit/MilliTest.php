<?php

namespace Tests\Unit;

use App\Support\Milli;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MilliTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function validInputs(): array
    {
        return [
            'whole number' => ['1234', 1_234_000],
            'german decimal comma' => ['1234,5', 1_234_500],
            'three decimals' => ['12,345', 12_345],
            'dot as decimal' => ['12.345', 12_345],
            'german thousands and decimals' => ['1.234,567', 1_234_567],
            'surrounding spaces' => [' 7 ', 7_000],
        ];
    }

    #[DataProvider('validInputs')]
    public function test_it_parses_meter_values_without_floats(string $input, int $expected): void
    {
        $this->assertSame($expected, Milli::parse($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidInputs(): array
    {
        return [
            'negative' => ['-5'],
            'four decimals' => ['1,2345'],
            'text' => ['abc'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_it_rejects_invalid_values(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Milli::parse($input);
    }

    public function test_it_formats_german_style(): void
    {
        $this->assertSame('1.234,567', Milli::format(1_234_567));
        $this->assertSame('12,50 €', Milli::formatCents(1250));
    }
}
