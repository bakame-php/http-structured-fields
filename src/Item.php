<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

final class Item implements StructuredField, SupportsParameters
{
    public function __construct(
        private Token|ByteSequence|int|float|string|bool $value,
        private Parameters                               $parameters,
    ) {
    }

    public static function fromField(string $field): self
    {
        $field = trim($field, ' ');
        [$value, $parameters] = match (true) {
            $field === '',
            1 === preg_match("/[\r\t\n]/", $field),
            1 === preg_match("/[^\x20-\x7E]/", $field) => throw new SyntaxError('Unexpected empty input'),
            1 === preg_match('/^(-?[0-9])/', $field) => self::parseNumber($field),
            $field[0] == '"' => self::parseString($field),
            $field[0] == ':' => self::parseBytesSequence($field),
            $field[0] == '?' => self::parseBoolean($field),
            1 === preg_match('/^([a-z*])/i', $field) => self::parseToken($field),
            default => throw new SyntaxError('Unknown item type'),
        };

        return new self($value, Parameters::fromField($parameters));
    }

    /**
     * @return array{0:Token, 1:string}
     */
    private static function parseToken(string $string): array
    {
        $regexp = '^(?<token>[a-z*][a-z0-9:\/'.preg_quote("!#$%&'*+-.^_`|~").']*)';
        if (!str_contains($string, ';')) {
            $regexp .= '$';
        }

        if (1 !== preg_match('/'.$regexp.'/i', $string, $matches)) {
            throw new SyntaxError('Invalid characters in token field.');
        }

        return [
            new Token($matches['token']),
            substr($string, strlen($matches['token'])),
        ];
    }

    /**
     * @return array{0:bool, 1:string}
     */
    private static function parseBoolean(string $string): array
    {
        if (1 !== preg_match('/^\?[01]/', $string)) {
            throw new SyntaxError('Invalid character in boolean');
        }

        return [$string[1] === '1', substr($string, 2)];
    }

    /**
     * @return array{0:ByteSequence, 1:string}
     */
    private static function parseBytesSequence(string $string): array
    {
        if (1 !== preg_match('/^:(?<bytes>[a-z0-9+\/=]*):/i', $string, $matches)) {
            throw new SyntaxError('Invalid character in byte sequence');
        }

        return [ByteSequence::fromEncoded($matches['bytes']), substr($string, strlen($matches[0]))];
    }

    /**
     * @return array{0:int|float, 1:string}
     */
    private static function parseNumber(string $string): array
    {
        if (1 !== preg_match('/^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)/', $string, $found)) {
            throw new SyntaxError('Invalid number format: `'.$string.'`');
        }

        $number = match (true) {
            1 === preg_match('/^-?\d{1,12}\.\d{1,3}$/', $found['number']) => (float) $found['number'],
            1 === preg_match('/^-?\d{1,15}$/', $found['number']) => (int) $found['number'],
            default => throw new SyntaxError('Number `'.$found['number'].'` contains too many digits'),
        };

        return [$number, substr($string, strlen($found['number']))];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function parseString(string $string): array
    {
        $string = substr($string, 1);

        $output_string = '';

        while (strlen($string)) {
            $char = $string[0];
            $string = substr($string, 1);

            if ($char === '"') {
                return [$output_string, $string];
            }

            if ($char !== '\\') {
                $output_string .= $char;
                continue;
            }

            if ($string === '') {
                throw new SyntaxError('Invalid end of string');
            }

            $char = $string[0];
            $string = substr($string, 1);
            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError('Invalid escaped character in string');
            }

            $output_string .= $char;
        }

        throw new SyntaxError('Invalid end of string');
    }

    public static function fromDecimal(float $value, Parameters|null $parameters = null): self
    {
        if (abs(floor($value)) > 999_999_999_999) {
            throw new SyntaxError('Integer portion of decimals is limited to 12 digits');
        }

        return new self($value, $parameters ?? new Parameters());
    }

    public static function fromString(string $value, Parameters|null $parameters = null): self
    {
        if (1 === preg_match('/[^\x20-\x7E]/i', $value)) {
            throw new SyntaxError('Invalid characters in string');
        }

        return new self($value, $parameters ?? new Parameters());
    }

    public static function fromToken(Token $value, Parameters|null $parameters = null): self
    {
        return new self($value, $parameters ?? new Parameters());
    }

    public static function fromByteSequence(ByteSequence $value, Parameters|null $parameters = null): self
    {
        return new self($value, $parameters ?? new Parameters());
    }

    public static function fromBoolean(bool $value, Parameters|null $parameters = null): self
    {
        return new self($value, $parameters ?? new Parameters());
    }

    public static function fromInteger(int $value, Parameters|null $parameters = null): self
    {
        if ($value > 999_999_999_999_999 || $value < -999_999_999_999_999) {
            throw new SyntaxError('Integers are limited to 15 digits');
        }

        return new self($value, $parameters ?? new Parameters());
    }

    public function value(): Token|ByteSequence|int|float|string|bool
    {
        return $this->value;
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function canonical(): string
    {
        $serializeValue = $this->serializeValue($this->value);
        $serializeParameters = $this->parameters->canonical();

        if ('' !== $serializeParameters) {
            return $serializeValue.$serializeParameters;
        }

        return $serializeValue;
    }

    private function serializeValue(Token|ByteSequence|int|float|string|bool $value): string
    {
        return match (true) {
            $value instanceof Token => $value->canonical(),
            $value instanceof ByteSequence => $value->canonical(),
            is_string($value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $value).'"',
            is_int($value) => (string) $value,
            is_float($value) => $this->serializeDecimal($value),
            default => '?'.($value ? '1' : '0'),
        };
    }

    private function serializeDecimal(float $value): string
    {
        // Casting to a string loses a digit on long numbers, but is preserved
        // by json_encode (e.g. 111111111111.111).
        /** @var string $result */
        $result = json_encode(round($value, 3, PHP_ROUND_HALF_EVEN));

        if (!str_contains($result, '.')) {
            $result .= '.0';
        }

        return $result;
    }
}
