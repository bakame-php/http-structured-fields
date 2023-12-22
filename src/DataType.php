<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

enum DataType: string
{
    case Dictionary = 'dictionary';
    case List = 'list';
    case Item = 'item';

    public function newStructuredField(string $input): StructuredField
    {
        return match ($this) {
            self::Dictionary => Dictionary::fromHttpValue($input),
            self::List => OuterList::fromHttpValue($input),
            self::Item => Item::fromHttpValue($input),
        };
    }
}
