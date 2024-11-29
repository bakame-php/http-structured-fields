<?php

namespace Bakame\Http\StructuredFields;

use Stringable;

use function preg_match;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.2
 * @internal normalize HTTP field key
 */
final readonly class MapKey
{
    private function __construct(public string $value)
    {
    }

    /**
     * @throws SyntaxError If the string is not a valid HTTP value field key
     */
    public static function from(Stringable|string|int $httpValue): self
    {
        $key = (string) $httpValue;
        $instance = self::fromStringBeginning($key);
        if ($instance->value !== $key) {
            throw new SyntaxError('No valid http value key could be extracted from "'.$httpValue.'".');
        }

        return $instance;
    }

    public static function tryFrom(Stringable|string|int $httpValue): ?self
    {
        try {
            return self::from($httpValue);
        } catch (SyntaxError $e) {
            return null;
        }
    }

    /**
     * @throws SyntaxError If the string does not start with a valid HTTP value field key
     */
    public static function fromStringBeginning(string $httpValue): self
    {
        if (1 !== preg_match('/^(?<key>[a-z*][a-z\d.*_-]*)/', $httpValue, $found)) {
            throw new SyntaxError('No valid http value key could be extracted from "'.$httpValue.'".');
        }

        return new self($found['key']);
    }
}
