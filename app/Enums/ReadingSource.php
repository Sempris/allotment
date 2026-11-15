<?php

namespace App\Enums;

enum ReadingSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Correction = 'correction';
    case Exchange = 'exchange';
    case Removal = 'removal';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuell erfasst',
            self::Import => 'CSV-Import',
            self::Correction => 'Korrektur',
            self::Exchange => 'Zählertausch',
            self::Removal => 'Ausbau',
        };
    }
}
