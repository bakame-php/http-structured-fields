<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeZone;
use Stringable;
use function in_array;
use function ltrim;
use function preg_match;
use function strlen;
use function substr;

/**
 * A class to parse HTTP Structured Fields from their HTTP textual representation according to RFC8941.
 *
 * Based on gapple\StructuredFields\Parser class in Structured Field Values for PHP v1.0.0.
 * @link https://github.com/gapple/structured-fields/blob/v1.0.0/src/Parser.php
 *
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2
 *
 * @internal Use Dictionary::fromHttpValue(), OrderedList::fromHttpValue(), InnerList::fromHttpValue() or Item::fromHttpValue() instead
 */
final class Parser
{
    /**
     * Returns an ordered list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1
     *
     * @return array<array{
     *     0:array<Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>,
     *     1:array<string,Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     * }|Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     */
    public static function parseList(Stringable|string $httpValue): array
    {
        $list = [];
        $remainder = ltrim((string) $httpValue, ' ');
        while ('' !== $remainder) {
            [$list[], $offset] = self::parseItemOrInnerList($remainder);
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, $offset);
        }

        return $list;
    }

    /**
     * Returns an ordered map represented as a PHP associative array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.2
     *
     * @return array<string, Item|ByteSequence|Token|DateTimeImmutable|array{
     *     0:array<Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>,
     *     1:array<string,Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     * }|bool|int|float|string>
     */
    public static function parseDictionary(Stringable|string $httpValue): array
    {
        $map = [];
        $remainder = ltrim((string) $httpValue, ' ');
        while ('' !== $remainder) {
            $key = MapKey::fromStringBeginning($remainder)->value;
            $remainder = substr($remainder, strlen($key));
            if ('' === $remainder || '=' !== $remainder[0]) {
                $remainder = '=?1'.$remainder;
            }

            [$map[$key], $offset] = self::parseItemOrInnerList(substr($remainder, 1));
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, ++$offset);
        }

        return $map;
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{
     *     0:array<Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>,
     *     1:array<string,Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     * }
     */
    public static function parseInnerList(Stringable|string $httpValue): array
    {
        $remainder = ltrim((string) $httpValue, ' ');
        if ('(' !== $remainder[0]) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list is missing a parenthesis.");
        }

        [$list, $offset] = self::parseInnerListValue($remainder);
        $remainder = self::removeOptionalWhiteSpaces(substr($remainder, $offset));
        if ('' !== $remainder) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list contains invalid data.");
        }

        return $list;
    }

    /**
     * Filter optional white spaces before and after comma.
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.3
     */
    private static function removeCommaSeparatedWhiteSpaces(string $httpValue, int $offset): string
    {
        $httpValue = self::removeOptionalWhiteSpaces(substr($httpValue, $offset));
        if ('' === $httpValue) {
            return $httpValue;
        }

        if (1 !== preg_match('/^(?<space>,[ \t]*)/', $httpValue, $found)) {
            throw new SyntaxError('The HTTP textual representation is missing an excepted comma.');
        }

        $httpValue = substr($httpValue, strlen($found['space']));
        if ('' === $httpValue) {
            throw new SyntaxError('Unexpected end of line for The HTTP textual representation.');
        }

        return $httpValue;
    }

    /**
     * Remove optional white spaces before field value.
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.3
     */
    private static function removeOptionalWhiteSpaces(string $httpValue): string
    {
        return ltrim($httpValue, " \t");
    }

    /**
     * Returns an Item value object or an inner list as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.1
     *
     * @return array{0: array{
     *     0:array<Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>,
     *     1:array<string,Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     * }|Item, 1:int}
     */
    private static function parseItemOrInnerList(string $httpValue): array
    {
        if ('(' === $httpValue[0]) {
            return self::parseInnerListValue($httpValue);
        }

        [$value, $offset] = self::parseBareItem($httpValue);
        $remainder = substr($httpValue, $offset);

        [$parameters, $offset] = self::parseParameters($remainder);
        $remainder = substr($remainder, $offset);

        return [Item::from($value, $parameters), strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{0:array{
     * 0:array<Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>,
     * 1:array<string,Item|ByteSequence|Token|DateTimeImmutable|bool|int|float|string>
     * }, 1:int}
     */
    private static function parseInnerListValue(string $httpValue): array
    {
        $list = [];
        $remainder = substr($httpValue, 1);
        while ('' !== $remainder) {
            $remainder = ltrim($remainder, ' ');

            if (')' === $remainder[0]) {
                $remainder = substr($remainder, 1);
                [$parameters, $offset] = self::parseParameters($remainder);
                $remainder = substr($remainder, $offset);

                return [[$list, $parameters], strlen($httpValue) - strlen($remainder)];
            }

            [$value, $offset] = self::parseBareItem($remainder);
            $remainder = substr($remainder, $offset);

            [$parameters, $offset] = self::parseParameters($remainder);
            $remainder = substr($remainder, $offset);

            $list[] = Item::from($value, $parameters);
            if ('' !== $remainder && !in_array($remainder[0], [' ', ')'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$remainder\" for a inner list is using invalid characters.");
            }
        }

        throw new SyntaxError("Unexpected end of line for The HTTP textual representation \"$remainder\" for a inner list.");
    }

    /**
     * Returns an Item value from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.1
     *
     * @return array{0:bool|float|int|string|ByteSequence|Token|DateTimeImmutable, 1:int}
     */
    private static function parseBareItem(string $httpValue): array
    {
        return match (true) {
            '"' === $httpValue[0] => self::parseString($httpValue),
            ':' === $httpValue[0] => self::parseByteSequence($httpValue),
            '?' === $httpValue[0] => self::parseBoolean($httpValue),
            '@' === $httpValue[0] => self::parseDate($httpValue),
            1 === preg_match('/^(-|\d)/', $httpValue) => self::parseNumber($httpValue),
            1 === preg_match('/^([a-z*])/i', $httpValue) => self::parseToken($httpValue),
            default => throw new SyntaxError('Unknown or unsupported string for The HTTP textual representation of an item.'),
        };
    }

    /**
     * Returns an parameters container represented as a PHP associative array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.2
     *
     * @return array{0:array<string, Token|ByteSequence|float|int|bool|DateTimeImmutable|string>, 1:int}
     */
    private static function parseParameters(string $httpValue): array
    {
        $map = [];
        $remainder = $httpValue;
        while ('' !== $remainder && ';' === $remainder[0]) {
            $remainder = ltrim(substr($remainder, 1), ' ');

            $key = MapKey::fromStringBeginning($remainder)->value;
            $map[$key] = true;

            $remainder = substr($remainder, strlen($key));
            if ('' !== $remainder && '=' === $remainder[0]) {
                $remainder = substr($remainder, 1);

                [$map[$key], $offset] = self::parseBareItem($remainder);
                $remainder = substr($remainder, $offset);
            }
        }

        return [$map, strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns a boolean from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.8
     *
     * @return array{0:bool, 1:int}
     */
    private static function parseBoolean(string $httpValue): array
    {
        if (1 !== preg_match('/^\?[01]/', $httpValue)) {
            throw new SyntaxError("Invalid character in the HTTP textual representation of a boolean value \"$httpValue\".");
        }

        return ['1' === $httpValue[1], 2];
    }

    /**
     * Returns a int or a float from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.4
     *
     * @return array{0:int|float, 1:int}
     */
    private static function parseNumber(string $httpValue): array
    {
        preg_match('/^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)/', $httpValue, $found);

        return match (true) {
            1 === preg_match('/^-?\d{1,12}\.\d{1,3}$/', $found['number']) => [(float) $found['number'], strlen($found['number'])],
            1 === preg_match('/^-?\d{1,15}$/', $found['number']) => [(int) $found['number'], strlen($found['number'])],
            default => throw new SyntaxError("The number format in the HTTP textual representation \"$httpValue\" contains too much digit."),
        };
    }

    /**
     * Returns DateTimeImmutable instance from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://httpwg.org/http-extensions/draft-ietf-httpbis-sfbis.html#name-dates
     *
     * @return array{0:DateTimeImmutable, 1:int}
     */
    private static function parseDate(string $httpValue): array
    {
        $number = substr($httpValue, 1);

        [$timestamp, $characters] = self::parseNumber($number);
        if (!is_int($timestamp)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a date contains invalid characters.");
        }

        return [(new DateTimeImmutable('NOW', new DateTimeZone('UTC')))->setTimestamp($timestamp), ++$characters];
    }


    /**
     * Returns a string from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.5
     *
     * @return array{0:string, 1:int}
     */
    private static function parseString(string $httpValue): array
    {
        $offset = 1;
        $httpValue = substr($httpValue, $offset);
        $output = '';

        while ('' !== $httpValue) {
            $char = $httpValue[0];
            $offset += 1;

            if ('"' === $char) {
                return [$output, $offset];
            }

            if (1 === preg_match("/[^\x20-\x7E]/", $char)) {
                throw new SyntaxError("Invalid character in the HTTP textual representation of a string \"$httpValue\".");
            }

            $httpValue = substr($httpValue, 1);
            if ('\\' !== $char) {
                $output .= $char;
                continue;
            }

            $char = $httpValue[0];
            $offset += 1;
            $httpValue = substr($httpValue, 1);
            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError("Invalid characters in the HTTP textual representation of a string \"$httpValue\".");
            }

            $output .= $char;
        }

        throw new SyntaxError("Invalid end of string in the HTTP textual representation of a string \"$httpValue\".");
    }

    /**
     * Returns a Token from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.6
     *
     * @return array{0:Token, 1:int}
     */
    private static function parseToken(string $httpValue): array
    {
        preg_match("/^(?<token>[a-z*][a-z\d:\/!#\$%&'*+\-.^_`|~]*)/i", $httpValue, $found);

        return [Token::fromString($found['token']), strlen($found['token'])];
    }

    /**
     * Returns a Byte Sequence from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.7
     *
     * @return array{0:ByteSequence, 1:int}
     */
    private static function parseByteSequence(string $httpValue): array
    {
        if (1 !== preg_match('/^(?<sequence>:(?<byte>[a-z\d+\/=]*):)/i', $httpValue, $matches)) {
            throw new SyntaxError("Invalid characters in the HTTP textual representation of a Byte Sequence \"$httpValue\".");
        }

        return [ByteSequence::fromEncoded($matches['byte']), strlen($matches['sequence'])];
    }
}
