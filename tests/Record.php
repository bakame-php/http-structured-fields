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
 * @phpstan-type CustomValueType array{
 *      __type: 'binary'|'date'|'displaystring'|'token',
 *      value: int|string
 *  }
 * @phpstan-type ItemValue CustomValueType|string|bool|int|float|null
 */
final class Record
{
    private function __construct(
        public readonly string $name,
        public readonly DataType $type,
        /** @var array<string> */
        public readonly array $raw,
        /** @var array<string> */
        public readonly array $canonical,
        public readonly bool $mustFail,
        public readonly bool $canFail,
        public readonly OuterList|Dictionary|Item|null $expected
    ) {
    }

    /**
     * @param RecordData $data
     */
    public static function fromDecoded(array $data): self
    {
        $data += ['canonical' => $data['raw'], 'must_fail' => false, 'can_fail' => false, 'expected' => []];
        $dataType = DataType::from($data['header_type']);

        return new self(
            $data['name'],
            $dataType,
            $data['raw'],
            $data['canonical'],
            $data['must_fail'],
            $data['can_fail'],
            self::parseExpected($dataType, $data['expected'])
        );
    }

    private static function parseExpected(DataType $dataType, array $expected): OuterList|Dictionary|Item|null
    {
        return match ($dataType) {
            DataType::Dictionary => self::parseDictionary($expected),
            DataType::List => self::parseList($expected),
            DataType::Item => self::parseItem($expected),
            default => throw new ValueError('The structured field can not be of the type "'.$dataType->value.'".'),
        };
    }

    /**
     * @param ItemValue $data
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
     * @param array<array{0:string, 1:ItemValue> $parameters
     */
    private static function parseParameters(array $parameters): Parameters
    {
        return Parameters::fromPairs(array_map(
            fn ($value) => [$value[0], self::parseValue($value[1])],
            $parameters
        ));
    }

    /**
     * @param array{0:ItemValue, 1:array<array{0:string, 1:ItemValue}>}|ItemValue $value
     */
    private static function parseItem(array|string|int $value): ?Item
    {
        return Item::tryfromPair(match (true) {
            !is_array($value) => [$value],
            [] === $value => [],
            1 === count($value) => [$value],
            default => [self::parseValue($value[0]), self::parseParameters($value[1])],
        });
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
