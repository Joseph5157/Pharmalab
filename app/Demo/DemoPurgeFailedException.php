<?php

namespace App\Demo;

use RuntimeException;
use Throwable;

/** A purge stage failed. Only the label is shown to the operator. */
class DemoPurgeFailedException extends RuntimeException
{
    public function __construct(public readonly string $label, Throwable $previous)
    {
        parent::__construct("Demo purge failed while deleting {$label}.", 0, $previous);
    }
}
