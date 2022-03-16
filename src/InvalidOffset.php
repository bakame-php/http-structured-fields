<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use OutOfBoundsException;

final class InvalidOffset extends OutOfBoundsException implements StructuredFieldError
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function dueToIndexNotFound(int $index): self
    {
        return new self('No member exists with the index `'.$index.'`.');
    }
    public static function dueToKeyNotFound(string $key): self
    {
        return new self('No member exists with the key `'.$key.'`.');
    }
}
