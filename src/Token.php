<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

final class Token implements StructuredField
{
    public function __construct(private string $value)
    {
        // Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
        // 3.2.6. Field Value Components
        // @see https://tools.ietf.org/html/rfc7230#section-3.2.6
        $tchar = preg_quote("!#$%&'*+-.^_`|~");

        if (1 !== preg_match('/^([a-z*][a-z0-9:\/'.$tchar.']*)$/i', $this->value)) {
            throw new SyntaxError('Invalid characters in token');
        }
    }

    public function canonical(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->value;
    }
}
