<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * A parser to create HTTP Structured Fields value objects from HTTP textual representation.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2
 *
 * @internal Use OrderedList::fromHttpValue() or Dictionary::fromHttpValue() instead
 */
final class Parser
{
    /**
     * Returns an OrderedList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1
     */
    public static function parseList(string $httpValue): OrderedList
    {
        $elements = [];
        $remainder = ltrim($httpValue, ' ');
        while ('' !== $remainder) {
            $elements[] = self::parseItemOrInnerList($remainder);
            $remainder = ltrim($remainder, " \t");

            if ('' === $remainder) {
                break;
            }

            if (1 !== preg_match('/^(,[ \t]*)/', $remainder, $found)) {
                throw new SyntaxError("The HTTP textual representation `$httpValue` for a list is missing an excepted comma.");
            }

            $remainder = substr($remainder, strlen($found[1]));

            if ('' === $remainder) {
                throw new SyntaxError("Unexpected end of line for The HTTP textual representation `$httpValue` for a list.");
            }
        }

        return OrderedList::fromElements($elements);
    }

    /**
     * Returns a Dictionary value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.2
     */
    public static function parseDictionary(string $httpValue): Dictionary
    {
        $elements = [];
        $remainder = ltrim($httpValue, ' ');
        while ('' !== $remainder) {
            $key = self::parseKey($remainder);
            if ('' !== $remainder && $remainder[0] === '=') {
                $remainder = substr($remainder, 1);
                $elements[$key] = self::parseItemOrInnerList($remainder);
            } else {
                $elements[$key] = Item::from(true, self::parseParameters($remainder));
            }

            $remainder = ltrim($remainder, " \t");
            if ('' === $remainder) {
                break;
            }

            if (1 !== preg_match('/^(,[ \t]*)/', $remainder, $found)) {
                throw new SyntaxError("The HTTP textual representation `$httpValue` for a dictionary is missing an excepted comma.");
            }

            $remainder = substr($remainder, strlen($found[1]));

            if ('' === $remainder) {
                throw new SyntaxError("Unexpected end of line for The HTTP textual representation `$httpValue` for a dictionary.");
            }
        }

        return Dictionary::fromAssociative($elements);
    }

    /**
     * Returns an Item or an InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.1
     */
    private static function parseItemOrInnerList(string &$httpValue): InnerList|Item
    {
        if ($httpValue[0] === '(') {
            return self::parseInnerList($httpValue);
        }

        return Item::from(self::parseBareItem($httpValue), self::parseParameters($httpValue));
    }

    /**
     * Returns an InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.1.2
     */
    private static function parseInnerList(string &$httpValue): InnerList
    {
        $elements = [];
        $httpValue = substr($httpValue, 1);
        while ('' !== $httpValue) {
            $httpValue = ltrim($httpValue, ' ');

            if ($httpValue[0] === ')') {
                $httpValue = substr($httpValue, 1);

                return InnerList::fromElements($elements, self::parseParameters($httpValue));
            }

            $elements[] = Item::from(self::parseBareItem($httpValue), self::parseParameters($httpValue));

            if ('' !== $httpValue && !in_array($httpValue[0], [' ', ')'], true)) {
                throw new SyntaxError("The HTTP textual representation `$httpValue` for a inner list is using invalid characters.");
            }
        }

        throw new SyntaxError("Unexpected end of line for The HTTP textual representation `$httpValue` for a inner list.");
    }

    /**
     * Returns a Item or an InnerList value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.1
     */
    private static function parseBareItem(string &$httpValue): bool|float|int|string|ByteSequence|Token
    {
        return match (true) {
            $httpValue === '' => throw new SyntaxError('Unexpected empty string for The HTTP textual representation of an item.'),
            1 === preg_match('/^(-|\d)/', $httpValue) => self::parseNumber($httpValue),
            $httpValue[0] == '"' =>  self::parseString($httpValue),
            $httpValue[0] == ':' => self::parseByteSequence($httpValue),
            $httpValue[0] == '?' => self::parseBoolean($httpValue),
            1 === preg_match('/^([a-z*])/i', $httpValue) => self::parseToken($httpValue),
            default => throw new SyntaxError('Unknown or unsupported string for The HTTP textual representation of an item.'),
        };
    }

    /**
     * Returns a Parameters value object from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.2
     */
    private static function parseParameters(string &$httpValue): Parameters
    {
        $parameters = [];

        while ('' !== $httpValue && ';' === $httpValue[0]) {
            $httpValue = ltrim(substr($httpValue, 1), ' ');

            $key = self::parseKey($httpValue);
            $parameters[$key] = true;

            if ('' !== $httpValue && '=' === $httpValue[0]) {
                $httpValue = substr($httpValue, 1);
                $parameters[$key] = self::parseBareItem($httpValue);
            }
        }

        return Parameters::fromAssociative($parameters);
    }

    /**
     * Returns a Dictionary or a Parameter string key from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.3.3
     */
    private static function parseKey(string &$httpValue): string
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*/', $httpValue, $matches)) {
            throw new SyntaxError('Invalid character in key');
        }

        $httpValue = substr($httpValue, strlen($matches[0]));

        return $matches[0];
    }

    /**
     * Returns a boolean from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.8
     */
    private static function parseBoolean(string &$httpValue): bool
    {
        if (1 !== preg_match('/^\?[01]/', $httpValue)) {
            throw new SyntaxError('Invalid character in boolean');
        }

        $value = $httpValue[1] === '1';

        $httpValue = substr($httpValue, 2);

        return $value;
    }

    /**
     * Returns a int or a float from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.4
     */
    private static function parseNumber(string &$httpValue): int|float
    {
        if (1 !== preg_match('/^(-?\d+(?:\.\d+)?)(?:[^\d.]|$)/', $httpValue, $number_matches)) {
            throw new SyntaxError('Invalid number format');
        }

        $input_number = $number_matches[1];
        $httpValue = substr($httpValue, strlen($input_number));

        return match (true) {
            1 === preg_match('/^-?\d{1,12}\.\d{1,3}$/', $input_number) => (float) $input_number,
            1 === preg_match('/^-?\d{1,15}$/', $input_number) => (int) $input_number,
            default => throw new SyntaxError('Number contains too many digits'),
        };
    }

    /**
     * Returns a string from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.5
     */
    private static function parseString(string &$httpValue): string
    {
        // parseString is only called if first character is a double quote, so
        // don't need to validate it here.
        $httpValue = substr($httpValue, 1);

        $output_string = '';

        while (strlen($httpValue)) {
            $char = $httpValue[0];
            $httpValue = substr($httpValue, 1);

            if ($char === '"') {
                return $output_string;
            }

            if (ord($char) <= 0x1f || ord($char) >= 0x7f) {
                throw new SyntaxError('Invalid character in string');
            }

            if ($char !== '\\') {
                $output_string .= $char;
                continue;
            }

            if ($httpValue === '') {
                throw new SyntaxError('Invalid end of string');
            }

            $char = $httpValue[0];
            $httpValue = substr($httpValue, 1);
            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError('Invalid escaped character in string');
            }

            $output_string .= $char;
        }

        throw new SyntaxError('Invalid end of string');
    }

    /**
     * Returns a Token from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.6
     */
    private static function parseToken(string &$httpValue): Token
    {
        preg_match('/^([a-z*][a-z0-9:\/'.preg_quote("!#$%&'*+-.^_`|~").']*)/i', $httpValue, $matches);

        $httpValue = substr($httpValue, strlen($matches[1]));

        return new Token($matches[1]);
    }

    /**
     * Returns a Byte Sequence from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.7
     */
    private static function parseByteSequence(string &$httpValue): ByteSequence
    {
        if (1 !== preg_match('/^:([a-z0-9+\/=]*):/i', $httpValue, $matches)) {
            throw new SyntaxError('Invalid character in byte sequence');
        }

        $httpValue = substr($httpValue, strlen($matches[0]));

        return ByteSequence::fromEncoded($matches[1]);
    }
}
