<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_replace;
use function round;
use function str_contains;
use function strlen;
use function substr;
use function trim;

final class Item implements StructuredField
{
    private function __construct(
        public readonly Token|ByteSequence|int|float|string|bool $value,
        public readonly Parameters $parameters
    ) {
    }

    /**
     * @param array{
     *     0:Token|ByteSequence|int|float|string|bool,
     *     1?:Parameters|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}>
     * } $pair
     */
    public static function fromPair(array $pair): self
    {
        if (!array_is_list($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair must be represented by an array as a list.');
        }

        $pair[1] = $pair[1] ?? [];
        if (2 !== count($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair first value should be the item value and the optional second value the item parameters.');
        }

        return self::from($pair[0], Parameters::fromPairs($pair[1]));
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param iterable<string,Item|ByteSequence|Token|bool|int|float|string> $parameters
     */
    public static function from(
        Token|ByteSequence|int|float|string|bool $value,
        iterable $parameters = []
    ): self {
        return new self(match (true) {
            is_int($value) => self::filterInteger($value),
            is_float($value) => self::filterDecimal($value),
            is_string($value) => self::filterString($value),
            default => $value,
        }, Parameters::fromAssociative($parameters));
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.2
     */
    public static function filterDecimal(float $value): float
    {
        if (abs(floor($value)) > 999_999_999_999) {
            throw new SyntaxError('Integer portion of decimals is limited to 12 digits');
        }

        return $value;
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.3
     */
    public static function filterString(string $value): string
    {
        if (1 === preg_match('/[^\x20-\x7E]/i', $value)) {
            throw new SyntaxError('The string `'.$value.'` contains invalid characters.');
        }

        return $value;
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.1
     */
    private static function filterInteger(int $value): int
    {
        if ($value > 999_999_999_999_999 || $value < -999_999_999_999_999) {
            throw new SyntaxError('Integers are limited to 15 digits');
        }

        return $value;
    }

    /**
     * Returns a new instance from an HTTP Header or Trailer value string
     * in compliance with RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
     */
    public static function fromHttpValue(string $httpValue): self
    {
        $itemString = trim($httpValue, ' ');

        [$value, $parameters] = match (true) {
            1 === preg_match("/[\r\t\n]|[^\x20-\x7E]/", $itemString),
            '' === $itemString => throw new SyntaxError("The HTTP textual representation `$httpValue` for an item contains invalid characters."),
            '"' === $itemString[0] => self::parseString($itemString),
            ':' === $itemString[0] => self::parseBytesSequence($itemString),
            '?' === $itemString[0] => self::parseBoolean($itemString),
            1 === preg_match('/^(-?\d)/', $itemString) => self::parseNumber($itemString),
            1 === preg_match('/^([a-z*])/i', $itemString) => self::parseToken($itemString),
            default => throw new SyntaxError("The HTTP textual representation `$httpValue` for an item is unknown or unsupported."),
        };

        return new self($value, Parameters::fromHttpValue($parameters));
    }

    /**
     * Parses an HTTP textual representation of an Item as a Token Data Type.
     *
     * @return array{0:Token, 1:string}
     */
    private static function parseToken(string $string): array
    {
        $regexp = "^(?<token>[a-z*][a-z0-9:\/\!\#\$%&'\*\+\-\.\^_`\|~]*)";
        if (!str_contains($string, ';')) {
            $regexp .= '$';
        }

        if (1 !== preg_match('/'.$regexp.'/i', $string, $found)) {
            throw new SyntaxError("The HTTP textual representation `$string` for a Token contains invalid characters.");
        }

        return [
            Token::fromString($found['token']),
            substr($string, strlen($found['token'])),
        ];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Boolean Data Type.
     *
     * @return array{0:bool, 1:string}
     */
    private static function parseBoolean(string $string): array
    {
        if (1 !== preg_match('/^\?[01]/', $string)) {
            throw new SyntaxError("The HTTP textual representation `$string` for a boolean contains invalid characters.");
        }

        return [$string[1] === '1', substr($string, 2)];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Byte Sequence Type.
     *
     * @return array{0:ByteSequence, 1:string}
     */
    private static function parseBytesSequence(string $string): array
    {
        if (1 !== preg_match('/^:(?<bytes>[a-z\d+\/=]*):/i', $string, $matches)) {
            throw new SyntaxError("The HTTP textual representation `$string` for a byte sequence contains invalid characters.");
        }

        return [ByteSequence::fromEncoded($matches['bytes']), substr($string, strlen($matches[0]))];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Number Data Type.
     *
     * @return array{0:int|float, 1:string}
     */
    private static function parseNumber(string $string): array
    {
        $regexp = '^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)';
        if (!str_contains($string, ';')) {
            $regexp = '^(?<number>-?\d+(?:\.\d+)?)$';
        }

        if (1 !== preg_match('/'.$regexp.'/', $string, $found)) {
            throw new SyntaxError("The HTTP textual representation `$string` for a number contains invalid characters.");
        }

        $number = match (true) {
            1 === preg_match('/^-?\d{1,12}\.\d{1,3}$/', $found['number']) => (float) $found['number'],
            1 === preg_match('/^-?\d{1,15}$/', $found['number']) => (int) $found['number'],
            default => throw new SyntaxError("The HTTP textual representation `$string` for a number contain too many digits."),
        };

        return [$number, substr($string, strlen($found['number']))];
    }

    /**
     * Parses an HTTP textual representation of an Item as a String Data Type.
     *
     * @return array{0:string, 1:string}
     */
    private static function parseString(string $string): array
    {
        $originalString = $string;
        $string = substr($string, 1);
        $returnValue = '';

        while ('' !== $string) {
            $char = $string[0];
            $string = substr($string, 1);

            if ($char === '"') {
                return [$returnValue, $string];
            }

            if ($char !== '\\') {
                $returnValue .= $char;
                continue;
            }

            if ($string === '') {
                throw new SyntaxError("The HTTP textual representation `$originalString` for a string contains an invalid end string.");
            }

            $char = $string[0];
            $string = substr($string, 1);
            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError("The HTTP textual representation `$originalString` for a string contains invalid characters.");
            }

            $returnValue .= $char;
        }

        throw new SyntaxError("The HTTP textual representation `$originalString` for a string contains an invalid end string.");
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1
     */
    public function toHttpValue(): string
    {
        return match (true) {
            is_string($this->value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $this->value).'"',
            is_int($this->value) => (string) $this->value,
            is_float($this->value) => $this->serializeDecimal($this->value),
            is_bool($this->value) => '?'.($this->value ? '1' : '0'),
            default => $this->value->toHttpValue(),
        }
        .$this->parameters->toHttpValue();
    }

    /**
     * Serialize the Item decimal value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1.5
     */
    private function serializeDecimal(float $value): string
    {
        /** @var string $result */
        $result = json_encode(round($value, 3, PHP_ROUND_HALF_EVEN));
        if (str_contains($result, '.')) {
            return $result;
        }

        return $result.'.0';
    }

    public function isInteger(): bool
    {
        return is_int($this->value);
    }

    public function isDecimal(): bool
    {
        return is_float($this->value);
    }

    public function isBoolean(): bool
    {
        return is_bool($this->value);
    }

    public function isString(): bool
    {
        return is_string($this->value);
    }

    public function isToken(): bool
    {
        return $this->value instanceof Token;
    }

    public function isByteSequence(): bool
    {
        return $this->value instanceof ByteSequence;
    }

    public function sanitize(): self
    {
        $this->parameters->sanitize();

        return $this;
    }
}
