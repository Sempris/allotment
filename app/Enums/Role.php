<?php

namespace App\Enums;

enum Role: string
{
    case Board = 'board';
    case Treasurer = 'treasurer';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Board => 'Vorstand',
            self::Treasurer => 'Kassierer/in',
            self::Tenant => 'Pächter/in',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Board => 'darf alles: Stammdaten, Ablesungen, Abrechnung, Benutzer',
            self::Treasurer => 'sieht alles, pflegt Tarife und Abrechnung, ändert keine Zählerstände',
            self::Tenant => 'sieht nur die eigene Parzelle',
        };
    }
}
