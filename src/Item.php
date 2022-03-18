<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

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

final class Item implements StructuredField, SupportsParameters
{
    private function __construct(
        private Token|ByteSequence|int|float|string|bool $value,
        private Parameters $parameters
    ) {
    }

    /**
     * @param array{value:Token|ByteSequence|int|float|string|bool, parameters:Parameters} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['value'], $properties['parameters']);
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
        $httpValue = trim($httpValue, ' ');
        [$value, $parameters] = match (true) {
            $httpValue === '',
            1 === preg_match("/[\r\t\n]/", $httpValue),
            1 === preg_match("/[^\x20-\x7E]/", $httpValue) => throw new SyntaxError("The HTTP textual representation `$httpValue` for an item contains invalid characters."),
            1 === preg_match('/^(-?[0-9])/', $httpValue) => self::parseNumber($httpValue),
            $httpValue[0] == '"' => self::parseString($httpValue),
            $httpValue[0] == ':' => self::parseBytesSequence($httpValue),
            $httpValue[0] == '?' => self::parseBoolean($httpValue),
            1 === preg_match('/^([a-z*])/i', $httpValue) => self::parseToken($httpValue),
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

        preg_match('/'.$regexp.'/i', $string, $found);

        return [
            new Token($found['token']),
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
        if (1 !== preg_match('/^:(?<bytes>[a-z0-9+\/=]*):/i', $string, $matches)) {
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
        if (1 !== preg_match('/^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)/', $string, $found)) {
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

    public function toHttpValue(): string
    {
        return $this->serializeValue($this->value).$this->parameters->toHttpValue();
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1
     */
    private function serializeValue(Token|ByteSequence|int|float|string|bool $value): string
    {
        return match (true) {
            $value instanceof Token => $value->toHttpValue(),
            $value instanceof ByteSequence => $value->toHttpValue(),
            is_string($value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $value).'"',
            is_int($value) => (string) $value,
            is_float($value) => $this->serializeDecimal($value),
            default => '?'.($value ? '1' : '0'),
        };
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

    public function value(): Token|ByteSequence|int|float|string|bool
    {
        return $this->value;
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function parameter(string $key): Item|Token|ByteSequence|float|int|bool|string
    {
        return $this->parameters->get($key)->value();
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
}
