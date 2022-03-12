<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

enum TestHeaderType: string
{
    case Dictionary = 'dictionary';
    case List = 'list';
    case Item = 'item';

    public function fromHttpValue(string $input): StructuredField
    {
        return match ($this) {
            self::Dictionary => Dictionary::fromHttpValue($input),
            self::List => OrderedList::fromHttpValue($input),
            self::Item => Item::fromHttpValue($input),
        };
    }
}
