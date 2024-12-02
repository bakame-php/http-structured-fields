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

    public static function dueToIndexNotFound(string|int $index): self
    {
        if (is_string($index)) {
            return new self('The member index can not be the string "'.$index.'".');
        }

        return new self('No member exists with the index "'.$index.'".');
    }

    public static function dueToKeyNotFound(string|int $key): self
    {
        if (is_int($key)) {
            return new self('The member key can not be the integer "'.$key.'".');
        }

        return new self('No member exists with the key "'.$key.'".');
    }

    public static function dueToMemberNotFound(string|int $offset): self
    {
        return new self('No member exists with the '.(is_int($offset) ? 'index' : 'key').' "'.$offset.'".');
    }
}
