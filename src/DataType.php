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
    public function serialize(iterable $data): string
    {
        return $this->create($data)->toHttpValue();
    }

    /**
     * @throws StructuredFieldError
     */
    public function create(iterable $data): StructuredField
    {
        return match ($this) {
            self::List => OuterList::fromPairs($data),
            self::InnerList => InnerList::fromPair([...$data]), /* @phpstan-ignore-line */
            self::Parameters => Parameters::fromPairs($data),
            self::Dictionary => Dictionary::fromPairs($data),
            self::Item => Item::fromPair([...$data]), /* @phpstan-ignore-line */
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 1.3.0
     * @codeCoverageIgnore
     *
     * @see DataType::serialize()
     */
    public function build(iterable $data): string
    {
        return $this->serialize($data);
    }
}
