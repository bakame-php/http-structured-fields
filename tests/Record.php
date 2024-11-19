<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use ValueError;

/**
 * @phpstan-type RecordData array{
 *     name: string,
 *     header_type: 'dictionary'|'list'|'item',
 *     raw: array<string>,
 *     canonical?: array<string>,
 *     must_fail?: bool,
 *     can_fail?: bool,
 *     expected?: array,
 * }
 * @phpstan-type itemValue array{__type:string, value:int|string}|string|bool|int|float|null
 */
final class Record
{
    private function __construct(
        public readonly string $name,
        /** @var 'dictionary'|'list'|'item' */
        public readonly string $type,
        /** @var array<string> */
        public readonly array $raw,
        /** @var array<string> */
        public readonly array $canonical,
        public readonly bool $mustFail,
        public readonly bool $canFail,
        public readonly OuterList|Dictionary|InnerList|Item|Parameters|null $expected
    ) {
    }

    /**
     * @param RecordData $data
     */
    public static function fromDecoded(array $data): self
    {
        $data += ['canonical' => $data['raw'], 'must_fail' => false, 'can_fail' => false, 'expected' => []];

        return new self(
            $data['name'],
            $data['header_type'],
            $data['raw'],
            $data['canonical'],
            $data['must_fail'],
            $data['can_fail'],
            self::parseExpected($data['header_type'], $data['expected'])
        );
    }

    private static function parseExpected(string $dataTypeValue, array $expected): OuterList|Dictionary|InnerList|Item|Parameters|null
    {
        return match (DataType::tryFrom($dataTypeValue)) {
            DataType::Dictionary => self::parseDictionary($expected),
            DataType::List => self::parseList($expected),
            DataType::Item => self::parseItem($expected),
            default => null,
        };
    }

    /**
     * @param itemValue $data
     */
    private static function parseValue(array|string|bool|int|float|null $data): Token|DateTimeImmutable|Bytes|DisplayString|string|bool|int|float|null
    {
        return match (true) {
            !is_array($data) => $data,
            2 !== count($data),
            !isset($data['__type'], $data['value']) => throw new ValueError('Unknown or unsupported type: '.json_encode($data)),
            default => match (Type::tryFrom($data['__type'])) {
                Type::Token => Token::fromString($data['value']),
                Type::Date => (new DateTimeImmutable())->setTimestamp((int) $data['value']),
                Type::DisplayString => DisplayString::fromDecoded($data['value']),
                Type::Bytes => Bytes::fromDecoded(base32_decode(encoded: $data['value'], strict: true)),
                default => throw new ValueError('Unknown or unsupported type: '.json_encode($data)),
            },
        };
    }

    /**
     * @param array<array{0:string, 1:itemValue> $parameters
     */
    private static function parseParameters(array $parameters): Parameters
    {
        return Parameters::fromPairs(array_map(
            fn ($value) => [$value[0], self::parseValue($value[1])],
            $parameters
        ));
    }

    /**
     * @param array{0:itemValue, 1:array<array{0:string, 1:itemValue}>}|itemValue $value
     */
    private static function parseItem(mixed $value): ?Item
    {
        return match (true) {
            !is_array($value) => Item::new($value),
            [] === $value => null,
            default => Item::new([
                self::parseValue($value[0]),
                self::parseParameters($value[1]),
            ]),
        };
    }

    private static function parseInnerList(array $innerListPair): InnerList
    {
        return InnerList::fromPair([
            array_map(fn ($value) => self::parseItem($value), $innerListPair[0]),
            self::parseParameters($innerListPair[1]),
        ]);
    }

    private static function parseList(array $listPairs): OuterList
    {
        return OuterList::fromPairs(array_map(
            fn ($value) => is_array($value[0]) && array_is_list($value[0]) ? self::parseInnerList($value) : self::parseItem($value),
            $listPairs
        ));
    }

    private static function parseDictionary(array $dictionaryPair): Dictionary
    {
        return Dictionary::fromPairs(array_map(
            fn ($value) => [
                $value[0],
                is_array($value[1][0]) && array_is_list($value[1][0]) ? self::parseInnerList($value[1]) : self::parseItem($value[1]),
            ],
            $dictionaryPair
        ));
    }
}
