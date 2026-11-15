<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A change to a user would lock the club out or erase who recorded readings.
 */
class UserChangeRejected extends RuntimeException
{
    public function __construct(public readonly string $field, string $reason)
    {
        parent::__construct($reason);
    }
}
