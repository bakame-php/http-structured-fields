<?php

namespace Bakame\Http\StructuredFields;

use function preg_match;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
 * @internal normalize HTTP field key
 */
final class MapKey
{
    private function __construct(
        public readonly string $value
    ) {
    }

    /**
     * @throws SyntaxError If the string is not a valid HTTP value field key
     */
    public static function fromString(string $httpValue): self
    {
        $instance = self::fromStringBeginning($httpValue);
        if ($instance->value !== $httpValue) {
            throw new SyntaxError("No valid http value key could be extracted from `$httpValue`.");
        }

        return $instance;
    }

    /**
     * @throws SyntaxError If the string does not start with a valid HTTP value field key
     */
    public static function fromStringBeginning(string $httpValue): self
    {
        if (1 !== preg_match('/^(?<key>[a-z*][a-z\d.*_-]*)/', $httpValue, $found)) {
            throw new SyntaxError("No valid http value key could be extracted from `$httpValue`.");
        }

        return new self($found['key']);
    }
}
