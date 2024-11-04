<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use BackedEnum;
use OutOfBoundsException;

final class InvalidOffset extends OutOfBoundsException implements StructuredFieldError
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function dueToIndexNotFound(BackedEnum|string|int $index): self
    {
        if ($index instanceof BackedEnum) {
            $index = $index->value;
        }

        if (is_string($index)) {
            return new self('The member index can not be the string "'.$index.'".');
        }

        return new self('No member exists with the index "'.$index.'".');
    }

    public static function dueToKeyNotFound(BackedEnum|string|int $key): self
    {
        if ($key instanceof BackedEnum) {
            $key = $key->value;
        }

        if (is_int($key)) {
            return new self('The member key can not be the integer "'.$key.'".');
        }

        return new self('No member exists with the key "'.$key.'".');
    }

    public static function dueToMemberNotFound(BackedEnum|string|int $key): self
    {
        if ($key instanceof BackedEnum) {
            $key = $key->value;
        }

        return new self('No member exists with the '.(is_int($key) ? 'index' : 'key').' "'.$key.'".');
    }
}
