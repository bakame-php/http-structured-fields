<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use Exception;
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
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2
 *
 * @see Dictionary::fromHttpValue()
 * @see Parameters::fromHttpValue()
 * @see OuterList::fromHttpValue()
 * @see InnerList::fromHttpValue()
 * @see Item::fromHttpValue()
 *
 * @internal Do not use directly this class as it's behaviour and return type
 *           MAY change significantly even during a major release cycle.
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

    public static function new(?Ietf $rfc = null): self
    {
        return new self($rfc ?? Ietf::Rfc9651);
    }

    public function __construct(private readonly Ietf $rfc)
    {
    }

    /**
     * Returns the data type value represented as a PHP type from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.4
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.5
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.6
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.7
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.8
     *
     * @throws Exception|SyntaxError
     */
    public function parseValue(Stringable|string $httpValue): ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool
    {
        $remainder = trim((string) $httpValue, ' ');
        if ('' === $remainder || 1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $remainder)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an item value contains invalid characters.");
        }

        [$value, $offset] = $this->extractValue($remainder);
        if ('' !== substr($remainder, $offset)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an item value contains invalid characters.");
        }

        return $value;
    }

    /**
     *
     * @throws Exception|SyntaxError
     *
     * @return array{0:SfType, 1:array<string, SfType>}
     */
    public function parseItem(Stringable|string $httpValue): array
    {
        $remainder = trim((string) $httpValue, ' ');
        if ('' === $remainder || 1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $remainder)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an item contains invalid characters.");
        }

        [$value, $offset] = $this->extractValue($remainder);
        $remainder = substr($remainder, $offset);
        if ('' !== $remainder && !str_contains($remainder, ';')) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an item contains invalid characters.");
        }

        return [$value, $this->parseParameters($remainder)];
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.2
     *
     * @throws SyntaxError|Exception
     *
     * @return array<string, SfType>
     */
    public function parseParameters(Stringable|string $httpValue): array
    {
        $remainder = trim((string) $httpValue);
        [$parameters, $offset] = $this->extractParametersValues($remainder);
        if (strlen($remainder) !== $offset) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for Parameters contains invalid characters.");
        }

        return $parameters;
    }

    /**
     * Returns an ordered list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1
     *
     * @throws SyntaxError|Exception
     *
     * @return array<array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
     */
    public function parseList(Stringable|string $httpValue): array
    {
        $list = [];
        $remainder = ltrim((string) $httpValue, ' ');
        while ('' !== $remainder) {
            [$list[], $offset] = $this->extractItemOrInnerList($remainder);
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, $offset);
        }

        return $list;
    }

    /**
     * Returns an ordered map represented as a PHP associative array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.2
     *
     * @throws SyntaxError|Exception
     *
     * @return array<string, array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
     */
    public function parseDictionary(Stringable|string $httpValue): array
    {
        $map = [];
        $remainder = ltrim((string) $httpValue, ' ');
        while ('' !== $remainder) {
            $key = MapKey::fromStringBeginning($remainder)->value;
            $remainder = substr($remainder, strlen($key));
            if ('' === $remainder || '=' !== $remainder[0]) {
                $remainder = '=?1'.$remainder;
            }

            [$map[$key], $offset] = $this->extractItemOrInnerList(substr($remainder, 1));
            $remainder = self::removeCommaSeparatedWhiteSpaces($remainder, ++$offset);
        }

        return $map;
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1.2
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}
     */
    public function parseInnerList(Stringable|string $httpValue): array
    {
        $remainder = ltrim((string) $httpValue, ' ');
        if ('(' !== $remainder[0]) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list is missing a parenthesis.");
        }

        [$list, $offset] = $this->extractInnerList($remainder);
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
    private static function removeCommaSeparatedWhiteSpaces(string $remainder, int $offset): string
    {
        $remainder = self::removeOptionalWhiteSpaces(substr($remainder, $offset));
        if ('' === $remainder) {
            return '';
        }

        if (1 !== preg_match(self::REGEXP_VALID_SPACE, $remainder, $found)) {
            throw new SyntaxError('The HTTP textual representation is missing an excepted comma.');
        }

        $remainder = substr($remainder, strlen($found['space']));

        if ('' === $remainder) {
            throw new SyntaxError('The HTTP textual representation has an unexpected end of line.');
        }

        return $remainder;
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
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1.1
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}, 1:int}
     */
    private function extractItemOrInnerList(string $httpValue): array
    {
        if ('(' === $httpValue[0]) {
            return $this->extractInnerList($httpValue);
        }

        [$item, $remainder] = $this->extractItem($httpValue);

        return [$item, strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1.2
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:array{0:array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}, 1:int}
     */
    private function extractInnerList(string $httpValue): array
    {
        $list = [];
        $remainder = substr($httpValue, 1);
        while ('' !== $remainder) {
            $remainder = ltrim($remainder, ' ');

            if (')' === $remainder[0]) {
                $remainder = substr($remainder, 1);
                [$parameters, $offset] = $this->extractParametersValues($remainder);
                $remainder = substr($remainder, $offset);

                return [[$list, $parameters], strlen($httpValue) - strlen($remainder)];
            }

            [$list[], $remainder] = $this->extractItem($remainder);

            if ('' !== $remainder && !in_array($remainder[0], [' ', ')'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list is using invalid characters.");
            }
        }

        throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a inner list has an unexpected end of line.");
    }

    /**
     * Returns an item represented as a PHP array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:array{0:SfType, 1:array<string, SfType>}, 1:string}
     */
    private function extractItem(string $remainder): array
    {
        [$value, $offset] = $this->extractValue($remainder);
        $remainder = substr($remainder, $offset);
        [$parameters, $offset] = $this->extractParametersValues($remainder);

        return [[$value, $parameters], substr($remainder, $offset)];
    }

    /**
     * Returns an item value from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.3.1
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:SfType, 1:int}
     */
    private function extractValue(string $httpValue): array
    {
        return match (true) {
            '"' === $httpValue[0] => self::extractString($httpValue),
            ':' === $httpValue[0] => self::extractByteSequence($httpValue),
            '?' === $httpValue[0] => self::extractBoolean($httpValue),
            '@' === $httpValue[0] => self::extractDate($httpValue, $this->rfc),
            str_starts_with($httpValue, '%"') => self::extractDisplayString($httpValue, $this->rfc),
            str_contains(self::FIRST_CHARACTER_RANGE_NUMBER, $httpValue[0]) => self::extractNumber($httpValue),
            str_contains(self::FIRST_CHARACTER_RANGE_TOKEN, $httpValue[0]) => self::extractToken($httpValue),
            default => throw new SyntaxError("The HTTP textual representation \"$httpValue\" for an item value is unknown or unsupported."),
        };
    }

    /**
     * Returns a parameters container represented as a PHP associative array from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.3.2
     *
     * @throws SyntaxError|Exception
     *
     * @return array{0:array<string, SfType>, 1:int}
     */
    private function extractParametersValues(Stringable|string $httpValue): array
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
                [$map[$key], $offset] = $this->extractValue($remainder);
                $remainder = substr($remainder, $offset);
            }
        }

        return [$map, strlen($httpValue) - strlen($remainder)];
    }

    /**
     * Returns a boolean from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.8
     *
     * @return array{0:bool, 1:int}
     */
    private static function extractBoolean(string $httpValue): array
    {
        return match (1) {
            preg_match(self::REGEXP_BOOLEAN, $httpValue) => ['1' === $httpValue[1], 2],
            default => throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Boolean contains invalid characters."),
        };
    }

    /**
     * Returns an int or a float from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.4
     *
     * @return array{0:int|float, 1:int}
     */
    private static function extractNumber(string $httpValue): array
    {
        if (1 !== preg_match(self::REGEXP_VALID_NUMBER, $httpValue, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Number contains invalid characters.");
        }

        return match (1) {
            preg_match(self::REGEXP_DECIMAL, $found['number']) => [(float) $found['number'], strlen($found['number'])],
            preg_match(self::REGEXP_INTEGER, $found['number']) => [(int) $found['number'], strlen($found['number'])],
            default => throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Number contains too much digit."),
        };
    }

    /**
     * Returns DateTimeImmutable instance from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://httpwg.org/http-extensions/draft-ietf-httpbis-sfbis.html#name-dates
     *
     * @throws SyntaxError
     * @throws Exception
     *
     * @return array{0:DateTimeImmutable, 1:int}
     */
    private static function extractDate(string $httpValue, Ietf $rfc): array
    {
        if (!$rfc->supports(Type::Date)) {
            throw new SyntaxError('The '.Type::Date->value.' type is not parsable by '.$rfc->value);
        }

        if (1 !== preg_match(self::REGEXP_DATE, $httpValue, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a Date contains invalid characters.");
        }

        return [new DateTimeImmutable('@'.$found['date']), strlen($found['date']) + 1];
    }

    /**
     * Returns a string from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.5
     *
     * @return array{0:string, 1:int}
     */
    private static function extractString(string $httpValue): array
    {
        $offset = 1;
        $remainder = substr($httpValue, $offset);
        $output = '';

        if (1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $remainder)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a String contains an invalid end string.");
        }

        while ('' !== $remainder) {
            $char = $remainder[0];
            $offset += 1;

            if ('"' === $char) {
                return [$output, $offset];
            }

            $remainder = substr($remainder, 1);

            if ('\\' !== $char) {
                $output .= $char;
                continue;
            }

            $char = $remainder[0] ?? '';
            $offset += 1;
            $remainder = substr($remainder, 1);

            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a String contains an invalid end string.");
            }

            $output .= $char;
        }

        throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a String contains an invalid end string.");
    }

    /**
     * Returns a string from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://datatracker.ietf.org/doc/html/draft-ietf-httpbis-sfbis#section-4.2.10
     *
     * @return array{0:DisplayString, 1:int}
     */
    private static function extractDisplayString(string $httpValue, Ietf $rfc): array
    {
        if (!$rfc->supports(Type::DisplayString)) {
            throw new SyntaxError('The '.Type::DisplayString->value.' type is not parsable by '.$rfc->value);
        }

        $offset = 2;
        $remainder = substr($httpValue, $offset);
        $output = '';

        if (1 === preg_match(self::REGEXP_INVALID_CHARACTERS, $remainder)) {
            throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a DisplayString contains an invalid character string.");
        }

        while ('' !== $remainder) {
            $char = $remainder[0];
            $offset += 1;

            if ('"' === $char) {
                return [DisplayString::fromEncoded($output), $offset];
            }

            $remainder = substr($remainder, 1);
            if ('%' !== $char) {
                $output .= $char;
                continue;
            }

            $octet = substr($remainder, 0, 2);
            $offset += 2;
            if (1 === preg_match('/^[0-9a-f]]{2}$/', $octet)) {
                throw new SyntaxError("The HTTP textual representation '$httpValue' for a DisplayString contains uppercased percent encoding sequence.");
            }

            $remainder = substr($remainder, 2);
            $output .= $char.$octet;
        }

        throw new SyntaxError("The HTTP textual representation \"$httpValue\" for a DisplayString contains an invalid end string.");
    }

    /**
     * Returns a Token from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.6
     *
     * @return array{0:Token, 1:int}
     */
    private static function extractToken(string $httpValue): array
    {
        preg_match(self::REGEXP_TOKEN, $httpValue, $found);

        $token = $found['token'] ?? '';

        return [Token::fromString($token), strlen($token)];
    }

    /**
     * Returns a Byte Sequence from an HTTP textual representation and the consumed offset in a tuple.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.7
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
