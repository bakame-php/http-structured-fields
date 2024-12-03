<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Exception;
use Stringable;

enum DataType: string
{
    case List = 'list';
    case InnerList = 'innerlist';
    case Parameters = 'parameters';
    case Dictionary = 'dictionary';
    case Item = 'item';

    /**
     * @throws SyntaxError|Exception
     */
    public function parse(Stringable|string $httpValue, Ietf $rfc = Ietf::Rfc9651): OuterList|InnerList|Parameters|Dictionary|Item
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
     * @throws SyntaxError|Exception
     */
    public function serialize(iterable $data, Ietf $rfc = Ietf::Rfc9651): string
    {
        return (match ($this) {
            self::List => OuterList::fromPairs($data),
            self::Dictionary => Dictionary::fromPairs($data),
            self::Item => Item::fromPair([...$data]),
            self::InnerList => InnerList::fromPair([...$data]),
            self::Parameters => Parameters::fromPairs($data),
        })->toHttpValue($rfc);
    }
}
