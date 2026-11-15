<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The bill of a plot cannot be issued (yet): incomplete, not reviewed, or no recipient.
 */
class InvoiceNotAvailable extends RuntimeException {}
