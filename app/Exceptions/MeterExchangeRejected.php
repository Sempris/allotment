<?php

namespace App\Exceptions;

use RuntimeException;

class MeterExchangeRejected extends RuntimeException
{
    /**
     * @param  string  $field  Form field the problem belongs to, so the UI can show it in place.
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly string $field, public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
