<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function in_array;
use function ltrim;
use function ord;
use function preg_match;
use function preg_quote;
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
     * Returns an OrderedList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1
     *
     * @return array<array{
     * 0:array<Item|ByteSequence|Token|bool|int|float|string>,
     * 1:array<string,Item|ByteSequence|Token|bool|int|float|string>
     * }|Item|ByteSequence|Token|bool|int|float|string>
     */
    public static function parseList(string $httpValue): array
    {
        $members = [];
        $remainder = ltrim($httpValue, ' ');
        while ('' !== $remainder) {
            [$members[], $offset] = self::parseItemOrInnerList($remainder);
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, $offset);
        }

        return $members;
    }

    /**
     * Returns a Dictionary value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.2
     *
     * @return array<string, Item|ByteSequence|Token|array{
     * 0:array<Item|ByteSequence|Token|bool|int|float|string>,
     * 1:array<string,Item|ByteSequence|Token|bool|int|float|string>
     * }|bool|int|float|string>
     */
    public static function parseDictionary(string $httpValue): array
    {
        $members = [];
        $remainder = ltrim($httpValue, ' ');
        while ('' !== $remainder) {
            [$key, $offset] = self::parseKey($remainder);
            $remainder = substr($remainder, $offset);
            if ('' === $remainder || '=' !== $remainder[0]) {
                $remainder = '=?1'.$remainder;
            }

            [$members[$key], $offset] = self::parseItemOrInnerList(substr($remainder, 1));
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, ++$offset);
        }

        return $members;
    }

    /**
     * Returns a InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{
     *  0:array<Item|ByteSequence|Token|bool|int|float|string>,
     *  1:array<string,Item|ByteSequence|Token|bool|int|float|string>
     * }
     */
    public static function parseInnerList(string $httpValue): array
    {
        $remainder = ltrim($httpValue, ' ');
        if ('(' !== $remainder[0]) {
            throw new SyntaxError("The HTTP textual representation `$httpValue` for a inner list is missing a parenthesis.");
        }

        [$members, $offset] = self::parseInnerListValue($remainder);
        $remainder = self::removeOptionalWhiteSpaces(substr($remainder, $offset));

        if ('' !== $remainder) {
            throw new SyntaxError("The HTTP textual representation `$httpValue` for a inner list contains invalid data.");
        }

        return $members;
    }

    /**
     * Filter optional white spaces before and after comma.
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.3
     */
    private static function removeCommaSeparatedWhiteSpaces(string $remainder, int $offset): string
    {
        $remainder = self::removeOptionalWhiteSpaces(substr($remainder, $offset));
        if ('' === $remainder) {
            return $remainder;
        }

        if (1 !== preg_match('/^(?<space>,[ \t]*)/', $remainder, $found)) {
            throw new SyntaxError('The HTTP textual representation is missing an excepted comma.');
        }

        $remainder = substr($remainder, strlen($found['space']));
        if ('' === $remainder) {
            throw new SyntaxError('Unexpected end of line for The HTTP textual representation.');
        }

        return $remainder;
    }

    /**
     * Remove optional white spaces before field value.
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.3
     */
    private static function removeOptionalWhiteSpaces(string $value): string
    {
        return ltrim($value, " \t");
    }

    /**
     * Returns an Item or an InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.1
     *
     * @return array{0: array{
     * 0:array<Item|ByteSequence|Token|bool|int|float|string>,
     * 1:array<string,Item|ByteSequence|Token|bool|int|float|string>
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
     * Returns an InnerList value object from an HTTP textual representation and the consumed offset.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{0:array{
     * 0:array<Item|ByteSequence|Token|bool|int|float|string>,
     * 1:array<string,Item|ByteSequence|Token|bool|int|float|string>
     * }, 1:int}
     */
    private static function parseInnerListValue(string $httpValue): array
    {
        $members = [];
        $remainder = substr($httpValue, 1);
        while ('' !== $remainder) {
            $remainder = ltrim($remainder, ' ');

            if (')' === $remainder[0]) {
                $remainder = substr($remainder, 1);
                [$parameters, $offset] = self::parseParameters($remainder);
                $remainder = substr($remainder, $offset);

                return [[$members, $parameters], strlen($httpValue) - strlen($remainder)];
            }

            [$value, $offset] = self::parseBareItem($remainder);
            $remainder = substr($remainder, $offset);

            [$parameters, $offset] = self::parseParameters($remainder);
            $remainder = substr($remainder, $offset);

            $members[] = Item::from($value, $parameters);

            if ('' !== $remainder && !in_array($remainder[0], [' ', ')'], true)) {
                throw new SyntaxError("The HTTP textual representation `$remainder` for a inner list is using invalid characters.");
            }
        }

        throw new SyntaxError("Unexpected end of line for The HTTP textual representation `$remainder` for a inner list.");
    }

    /**
     * Returns a Item or an InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.1
     *
     * @return array{0:bool|float|int|string|ByteSequence|Token, 1:int}
     */
    private static function parseBareItem(string $httpValue): array
    {
        return match (true) {
            '' === $httpValue => throw new SyntaxError('Unexpected empty string for The HTTP textual representation of an item.'),
            1 === preg_match('/^(-|\d)/', $httpValue) => self::parseNumber($httpValue),
            '"' === $httpValue[0] => self::parseString($httpValue),
            ':' === $httpValue[0] => self::parseByteSequence($httpValue),
            '?' === $httpValue[0] => self::parseBoolean($httpValue),
            1 === preg_match('/^([a-z*])/i', $httpValue) => self::parseToken($httpValue),
            default => throw new SyntaxError('Unknown or unsupported string for The HTTP textual representation of an item.'),
        };
    }

    /**
     * Returns a Parameters value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.2
     *
     * @return array{0:array<array-key, Token|ByteSequence|float|int|bool|string>, 1:int}
     */
    private static function parseParameters(string $httpValue): array
    {
        $parameters = [];
        $remainder = $httpValue;
        while ('' !== $remainder && ';' === $remainder[0]) {
            $remainder = ltrim(substr($remainder, 1), ' ');

            [$key, $keyOffset] = self::parseKey($remainder);
            $parameters[$key] = true;

            $remainder = substr($remainder, $keyOffset);
            if ('' !== $remainder && '=' === $remainder[0]) {
                $remainder = substr($remainder, 1);

                [$parameters[$key], $offset] = self::parseBareItem($remainder);
                $remainder = substr($remainder, $offset);
            }
        }

        return [$parameters, strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns a Dictionary or a Parameter string key from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.3
     *
     * @return array{0:string, 1:int}
     */
    private static function parseKey(string $httpValue): array
    {
        if (1 !== preg_match('/^(?<key>[a-z*][a-z0-9.*_-]*)/', $httpValue, $matches)) {
            throw new SyntaxError("Invalid character in the HTTP textual representation of a key `$httpValue`.");
        }

        return [$matches['key'], strlen($matches['key'])];
    }

    /**
     * Returns a boolean from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.8
     *
     * @return array{0:bool, 1:int}
     */
    private static function parseBoolean(string $httpValue): array
    {
        if (1 !== preg_match('/^\?[01]/', $httpValue)) {
            throw new SyntaxError("Invalid character in the HTTP textual representation of a boolean value `$httpValue`.");
        }

        return ['1' === $httpValue[1], 2];
    }

    /**
     * Returns a int or a float from an HTTP textual representation.
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
            default => throw new SyntaxError("The number format in the HTTP textual representation `$httpValue` contains too much digit."),
        };
    }

    /**
     * Returns a string from an HTTP textual representation.
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

            if (ord($char) <= 0x1f || ord($char) >= 0x7f) {
                throw new SyntaxError("Invalid character in the HTTP textual representation of a string `$httpValue`.");
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
                throw new SyntaxError("Invalid characters in the HTTP textual representation of a string `$httpValue`.");
            }

            $output .= $char;
        }

        throw new SyntaxError("Invalid end of string in the HTTP textual representation of a string `$httpValue`.");
    }

    /**
     * Returns a Token from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.6
     *
     * @return array{0:Token, 1:int}
     */
    private static function parseToken(string $httpValue): array
    {
        preg_match('/^(?<token>[a-z*][a-z0-9:\/'.preg_quote("!#$%&'*+-.^_`|~").']*)/i', $httpValue, $found);

        return [Token::fromString($found['token']), strlen($found['token'])];
    }

    /**
     * Returns a Byte Sequence from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.7
     *
     * @return array{0:ByteSequence, 1:int}
     */
    private static function parseByteSequence(string $httpValue): array
    {
        if (1 !== preg_match('/^(?<sequence>:(?<byte>[a-z0-9+\/=]*):)/i', $httpValue, $matches)) {
            throw new SyntaxError("Invalid characters in the HTTP textual representation of a Byte Sequence `$httpValue`.");
        }

        return [ByteSequence::fromEncoded($matches['byte']), strlen($matches['sequence'])];
    }
}
