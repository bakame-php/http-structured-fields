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
    public function fromRfc9651(Stringable|string $httpValue): StructuredField
    {
        return $this->parse($httpValue, Ietf::Rfc9651);
    }

    /**
     * @throws StructuredFieldError
     */
    public function fromRfc8941(Stringable|string $httpValue): StructuredField
    {
        return $this->parse($httpValue, Ietf::Rfc8941);
    }

    /**
     * @throws StructuredFieldError
     */
    public function toRfc9651(iterable $data): string
    {
        return $this->serialize($data, Ietf::Rfc9651);
    }

    /**
     * @throws StructuredFieldError
     */
    public function toRfc8941(iterable $data): string
    {
        return $this->serialize($data, Ietf::Rfc8941);
    }

    /**
     * @throws StructuredFieldError
     */
    public function parse(Stringable|string $httpValue, ?Ietf $rfc = null): StructuredField
    {
        $parser = new Parser($rfc);

        return match ($this) {
            self::List => OuterList::fromHttpValue($httpValue, $parser),
            self::InnerList => InnerList::fromHttpValue($httpValue, $parser),
            self::Parameters => Parameters::fromHttpValue($httpValue, $parser),
            self::Dictionary => Dictionary::fromHttpValue($httpValue, $parser),
            self::Item => Item::fromHttpValue($httpValue, $parser),
        };
    }

    /**
     * @throws StructuredFieldError
     */
    public function serialize(iterable $data, ?Ietf $rfc = null): string
    {
        return $this->create($data)->toHttpValue($rfc);
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
    public function build(iterable $data, ?Ietf $rfc = null): string
    {
        return $this->serialize($data, $rfc);
    }
}
