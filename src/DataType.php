<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

enum DataType: string
{
    case Item = 'item';
    case Parameters = 'parameters';
    case InnerList = 'innerlist';
    case List = 'list';
    case Dictionary = 'dictionary';

    /**
     * @throws StructuredFieldError
     */
    public function parse(Stringable|string $httpValue): StructuredField
    {
        return match ($this) {
            self::Dictionary => Dictionary::fromHttpValue($httpValue),
            self::Parameters => Parameters::fromHttpValue($httpValue),
            self::List => OuterList::fromHttpValue($httpValue),
            self::InnerList => InnerList::fromHttpValue($httpValue),
            self::Item => Item::fromHttpValue($httpValue),
        };
    }

    /**
     * @throws StructuredFieldError
     */
    public function build(iterable $data): string
    {
        return match ($this) {
            self::Dictionary => Dictionary::fromPairs($data)->toHttpValue(),
            self::Parameters => Parameters::fromPairs($data)->toHttpValue(),
            self::List => OuterList::fromPairs($data)->toHttpValue(),
            self::InnerList => InnerList::fromPair([...$data])->toHttpValue(), /* @phpstan-ignore-line */
            self::Item => Item::fromPair([...$data])->toHttpValue(), /* @phpstan-ignore-line */
        };
    }
}
