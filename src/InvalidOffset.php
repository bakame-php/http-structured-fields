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

    public static function dueToIndexNotFound(MapKey|string|int $index): self
    {
        if (is_string($index)) {
            return new self('The member index can not be the string "'.$index.'".');
        }

        return new self('No member exists with the index "'.($index instanceof MapKey ? $index->value : $index).'".');
    }

    public static function dueToKeyNotFound(MapKey|string|int $key): self
    {
        if (is_int($key)) {
            return new self('The member key  can not be the integer "'.$key.'".');
        }

        return new self('No member exists with the key "'.($key instanceof MapKey ? $key->value : $key).'".');
    }
}
