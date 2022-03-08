<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use OutOfBoundsException;
use Throwable;

final class InvalidOffset extends OutOfBoundsException implements StructuredFieldError
{
    private function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function dueToIndexNotFound(int $index): self
    {
        return new self('No element exists with the index `'.$index.'`.');
    }
    public static function dueToKeyNotFound(string $key): self
    {
        return new self('No element exists with the key `'.$key.'`.');
    }
}
