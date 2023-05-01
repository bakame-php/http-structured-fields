<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use Stringable;
use function in_array;
use function ltrim;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;

/**
 * A class to parse HTTP Structured Fields from their HTTP textual representation according to RFC8941.
 *
 * Based on gapple\StructuredFields\Parser class in Structured Field Values for PHP v1.0.0.
 *
 * @link https://github.com/gapple/structured-fields/blob/v1.0.0/src/Parser.php
 *
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2
 *
 * @internal Use Dictionary::fromHttpValue(),
 *               Parameters::fromHttpValue(),
 *               OuterList::fromHttpValue(),
 *               InnerList::fromHttpValue()
 *            or Item::fromHttpValue() instead
 *
 * @phpstan-import-type SfType from StructuredField
 */
final class Parser
{
    private const REGEXP_BYTE_SEQUENCE = '/^(?<sequence>:(?<byte>[a-z\d+\/=]*):)/i';
    private const REGEXP_BOOLEAN = '/^\?[01]/';
    private const REGEXP_DATE = '/^@(?<date>-?\d{1,15})(?:[^\d.]|$)/';
    private const REGEXP_DECIMAL = '/^-?\d{1,12}\.\d{1,3}$/';
    private const REGEXP_INTEGER = '/^-?\d{1,15}$/';
    private const REGEXP_TOKEN = "/^(?<token>[a-z*][a-z\d:\/!#\$%&'*+\-.^_`|~]*)/i";
    private const REGEXP_INVALID_CHARACTERS = "/[\r\t\n]|[^\x20-\x7E]/";
    private const REGEXP_VALID_NUMBER = '/^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)/';
    private const REGEXP_VALID_SPACE = '/^(?<space>,[ \t]*)/';
    private const FIRST_CHARACTER_RANGE_NUMBER = '-1234567890';
    private const FIRST_CHARACTER_RANGE_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ*';

    /**
     * @return array{0:SfType, 1:array<string, SfType>}
     */
    public static function parseItem(Stringable|string $httpValue): array
    {
        $itemString = trim((string) $httpValue, ' ');
        if ('' === $itemString || 1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $itemString)) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item contains invalid characters.');
        }

        [$value, $offset] = self::extractValue($itemString);
        $remainder = substr($itemString, $offset);
        if ('' !== $remainder && !str_contains($remainder, ';')) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item contains invalid characters.');
        }

        return [$value, self::parseParameters($remainder)];
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError If the string is not a valid
     *
     * @return array<string, SfType>
     */
    public static function parseParameters(Stringable|string $httpValue): array
    {
        $parameterString = trim((string) $httpValue);
        [$parameters, $offset] = self::extractParametersValues($parameterString);
        if (strlen($parameterString) !== $offset) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for Parameters contains invalid characters.');
        }

        return $parameters;
    }

    /**
     * Returns an ordered list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1
     *
     * @return array<array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
     */
    public static function parseList(Stringable|string $httpValue): array
    {
        $list = [];
        $remainder = ltrim((string) $httpValue, ' ');
        while ('' !== $remainder) {
            [$list[], $offset] = self::extractItemOrInnerList($remainder);
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, $offset);
        }

        return $list;
    }

    /**
     * Returns an ordered map represented as a PHP associative array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.2
     *
     * @return array<string, array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
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

            [$map[$key], $offset] = self::extractItemOrInnerList(substr($remainder, 1));
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, ++$offset);
        }

        return $map;
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{0:array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}
     */
    public static function parseInnerList(Stringable|string $httpValue): array
    {
        $remainder = ltrim((string) $httpValue, ' ');
        if ('(' !== $remainder[0]) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list is missing a parenthesis.");
        }

        [$list, $offset] = self::extractInnerList($remainder);
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

        if (1 !== preg_match(self::REGEXP_VALID_SPACE, $httpValue, $found)) {
            throw new SyntaxError('The HTTP textual representation is missing an excepted comma.');
        }

        $httpValue = substr($httpValue, strlen($found['space']));
        if ('' === $httpValue) {
            throw new SyntaxError('The HTTP textual representation has an unexpected end of line.');
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
     * Returns an item or an inner list as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.1
     *
     * @return array{0:array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}, 1:int}
     */
    private static function extractItemOrInnerList(string $httpValue): array
    {
        if ('(' === $httpValue[0]) {
            return self::extractInnerList($httpValue);
        }

        [$item, $remainder] = self::extractItem($httpValue);

        return [$item, strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     *
     * @return array{0:array{0:array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}, 1:int}
     */
    private static function extractInnerList(string $httpValue): array
    {
        $list = [];
        $remainder = substr($httpValue, 1);
        while ('' !== $remainder) {
            $remainder = ltrim($remainder, ' ');

            if (')' === $remainder[0]) {
                $remainder = substr($remainder, 1);
                [$parameters, $offset] = self::extractParametersValues($remainder);
                $remainder = substr($remainder, $offset);

                return [[$list, $parameters], strlen($httpValue) - strlen($remainder)];
            }

            [$list[], $remainder] = self::extractItem($remainder);

            if ('' !== $remainder && !in_array($remainder[0], [' ', ')'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$remainder\" for a inner list is using invalid characters.");
            }
        }

        throw new SyntaxError("The HTTP textual representation \"$remainder\" for a inner list has an unexpected end of line.");
    }

    /**
     * Returns an item represented as a PHP array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @return array{0:array{0:SfType, 1:array<string, SfType>}, 1:string}
     */
    private static function extractItem(string $remainder): array
    {
        [$value, $offset] = self::extractValue($remainder);
        $remainder = substr($remainder, $offset);
        [$parameters, $offset] = self::extractParametersValues($remainder);

        return [[$value, $parameters], substr($remainder, $offset)];
    }

    /**
     * Returns an item value from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.1
     *
     * @return array{0:SfType, 1:int}
     */
    private static function extractValue(string $httpValue): array
    {
        return match (true) {
            '"' === $httpValue[0] => self::extractString($httpValue),
            ':' === $httpValue[0] => self::extractByteSequence($httpValue),
            '?' === $httpValue[0] => self::extractBoolean($httpValue),
            '@' === $httpValue[0] => self::extractDate($httpValue),
            str_contains(self::FIRST_CHARACTER_RANGE_NUMBER, $httpValue[0]) => self::extractNumber($httpValue),
            str_contains(self::FIRST_CHARACTER_RANGE_TOKEN, $httpValue[0]) => self::extractToken($httpValue),
            default => throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an Item is unknown or unsupported."),
        };
    }

    /**
     * Returns a parameters container represented as a PHP associative array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.2
     *
     * @return array{0:array<string, SfType>, 1:int}
     */
    private static function extractParametersValues(Stringable|string $httpValue): array
    {
        $map = [];
        $httpValue = (string) $httpValue;
        $remainder = $httpValue;
        while ('' !== $remainder && ';' === $remainder[0]) {
            $remainder = ltrim(substr($remainder, 1), ' ');

            $key = MapKey::fromStringBeginning($remainder)->value;
            $map[$key] = true;

            $remainder = substr($remainder, strlen($key));
            if ('' !== $remainder && '=' === $remainder[0]) {
                $remainder = substr($remainder, 1);

                [$map[$key], $offset] = self::extractValue($remainder);
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
    private static function extractBoolean(string $httpValue): array
    {
        if (1 !== preg_match(self::REGEXP_BOOLEAN, $httpValue)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Boolean contains invalid characters.");
        }

        return ['1' === $httpValue[1], 2];
    }

    /**
     * Returns an int or a float from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.4
     *
     * @return array{0:int|float, 1:int}
     */
    private static function extractNumber(string $httpValue): array
    {
        if (1 !== preg_match(self::REGEXP_VALID_NUMBER, $httpValue, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Number contains invalid characters.");
        }

        return match (true) {
            1 === preg_match(self::REGEXP_DECIMAL, $found['number']) => [(float) $found['number'], strlen($found['number'])],
            1 === preg_match(self::REGEXP_INTEGER, $found['number']) => [(int) $found['number'], strlen($found['number'])],
            default => throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Number contains too much digit."),
        };
    }

    /**
     * Returns DateTimeImmutable instance from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://httpwg.org/http-extensions/draft-ietf-httpbis-sfbis.html#name-dates
     *
     * @return array{0:DateTimeImmutable, 1:int}
     */
    private static function extractDate(string $httpValue): array
    {
        if (1 !== preg_match(self::REGEXP_DATE, $httpValue, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Date contains invalid characters.");
        }

        return [new DateTimeImmutable('@'.$found['date']), strlen($found['date']) + 1];
    }

    /**
     * Returns a string from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.5
     *
     * @return array{0:string, 1:int}
     */
    private static function extractString(string $httpValue): array
    {
        $offset = 1;
        $originalHttpValue = $httpValue;
        $httpValue = substr($httpValue, $offset);
        $output = '';

        while ('' !== $httpValue) {
            $char = $httpValue[0];
            $offset += 1;

            if ('"' === $char) {
                return [$output, $offset];
            }

            if (1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $char)) {
                throw new SyntaxError("The HTTP textual representation \"$originalHttpValue\" for a String contains an invalid end string.");
            }

            $httpValue = substr($httpValue, 1);

            if ('\\' !== $char) {
                $output .= $char;
                continue;
            }

            $char = $httpValue[0] ?? '';
            $offset += 1;
            $httpValue = substr($httpValue, 1);

            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$originalHttpValue\" for a String contains an invalid end string.");
            }

            $output .= $char;
        }

        throw new SyntaxError("The HTTP textual representation \"$originalHttpValue\" for a String contains an invalid end string.");
    }

    /**
     * Returns a Token from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.6
     *
     * @return array{0:Token, 1:int}
     */
    private static function extractToken(string $httpValue): array
    {
        preg_match(self::REGEXP_TOKEN, $httpValue, $found);

        return [Token::fromString($found['token']), strlen($found['token'])];
    }

    /**
     * Returns a Byte Sequence from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.7
     *
     * @return array{0:ByteSequence, 1:int}
     */
    private static function extractByteSequence(string $httpValue): array
    {
        if (1 !== preg_match(self::REGEXP_BYTE_SEQUENCE, $httpValue, $matches)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Byte Sequence contains invalid characters.");
        }

        return [ByteSequence::fromEncoded($matches['byte']), strlen($matches['sequence'])];
    }
}
