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
     * @throws SyntaxError
     */
    public function fromRfc9651(Stringable|string $httpValue): OuterList|InnerList|Parameters|Dictionary|Item
    {
        return $this->parse($httpValue, Ietf::Rfc9651);
    }

    /**
     * @throws SyntaxError
     */
    public function fromRfc8941(Stringable|string $httpValue): OuterList|InnerList|Parameters|Dictionary|Item
    {
        return $this->parse($httpValue, Ietf::Rfc8941);
    }

    /**
     * @throws SyntaxError
     */
    public function parse(Stringable|string $httpValue, ?Ietf $rfc = null): OuterList|InnerList|Parameters|Dictionary|Item
    {
        return match ($this) {
            self::List => OuterList::fromHttpValue($httpValue, $rfc),
            self::Dictionary => Dictionary::fromHttpValue($httpValue, $rfc),
            self::Item => Item::fromHttpValue($httpValue, $rfc),
            self::InnerList => InnerList::fromHttpValue($httpValue, $rfc),
            self::Parameters => Parameters::fromHttpValue($httpValue, $rfc),
        };
    }

    /**
     * @throws SyntaxError
     */
    public function toRfc9651(iterable $data): string
    {
        return $this->serialize($data, Ietf::Rfc9651);
    }

    /**
     * @throws SyntaxError
     */
    public function toRfc8941(iterable $data): string
    {
        return $this->serialize($data, Ietf::Rfc8941);
    }

    /**
     * @throws SyntaxError
     */
    public function serialize(iterable $data, ?Ietf $rfc = null): string
    {
        return $this->create($data)->toHttpValue($rfc);
    }

    /**
     * @throws SyntaxError
     */
    public function create(iterable $data): OuterList|InnerList|Parameters|Dictionary|Item
    {
        return match ($this) {
            self::List => OuterList::fromPairs($data),
            self::Dictionary => Dictionary::fromPairs($data),
            self::Item => Item::fromPair([...$data]),
            self::InnerList => InnerList::fromPair([...$data]),
            self::Parameters => Parameters::fromPairs($data),
        };
    }
}
