<?php

namespace App\Exceptions;

use RuntimeException;

class CaseNotReadyForSubmissionException extends RuntimeException
{
    /** @param list<array{section: string, message: string}> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Case is not ready for submission.');
    }

    /** @return list<array{section: string, message: string}> */
    public function errors(): array
    {
        return $this->errors;
    }
}
