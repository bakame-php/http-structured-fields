<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

enum DataType: string
{
    case List = 'list';
    case InnerList = 'innerlist';
    case Parameters = 'parameters';
    case Dictionary = 'dictionary';
    case Item = 'item';

    /**
     * @throws StructuredFieldError
     */
    public function parse(Stringable|string $httpValue): StructuredField
    {
        return match ($this) {
            self::List => OuterList::fromHttpValue($httpValue),
            self::InnerList => InnerList::fromHttpValue($httpValue),
            self::Parameters => Parameters::fromHttpValue($httpValue),
            self::Dictionary => Dictionary::fromHttpValue($httpValue),
            self::Item => Item::fromHttpValue($httpValue),
        };
    }

    /**
     * @throws StructuredFieldError
     */
    public function build(iterable $data): string
    {
        return (match ($this) {
            self::List => OuterList::fromPairs($data),
            self::InnerList => InnerList::fromPair([...$data]), /* @phpstan-ignore-line */
            self::Parameters => Parameters::fromPairs($data),
            self::Dictionary => Dictionary::fromPairs($data),
            self::Item => Item::fromPair([...$data]), /* @phpstan-ignore-line */
        })->toHttpValue();
    }
}
