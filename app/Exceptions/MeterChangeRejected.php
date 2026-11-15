<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A change to a meter (exchange, removal, edit, deletion) would contradict its readings.
 */
class MeterChangeRejected extends RuntimeException
{
    /**
     * @param  string  $field  What the problem refers to (meter, type, date, final_value,
     *                         installed_on, initial_value), so the UI can show it in place.
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly string $field, public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
