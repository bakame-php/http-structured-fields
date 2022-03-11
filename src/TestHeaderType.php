<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

enum TestHeaderType: string
{
    case Dictionary = 'dictionary';
    case List = 'list';
    case Item = 'item';

    public function fromField(string $input): StructuredField
    {
        return match ($this) {
            self::Dictionary => Dictionary::fromField($input),
            self::List => OrderedList::fromField($input),
            self::Item => Item::fromField($input),
        };
    }
}
