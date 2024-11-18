<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @internal The interface MUST not be implemented outside the package codebase
 */
interface StructuredField extends Stringable
{
    /**
     * Returns a new instance from an HTTP Header or Trailer value string in compliance to an RFC.
     *
     * @throws StructuredFieldError If the HTTP value can not be parsed
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self;

    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     *
     * @throws StructuredFieldError If the object can not be serialized
     */
    public function toHttpValue(?Ietf $rfc = null): string;

    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value
     * using the last accepted RFC protocol.
     */
    public function __toString(): string;

    /**
     * Tells whether the object is identical as the one submitted.
     * Returns true on success, false otherwise.
     *
     * Two objects are considered equals if they are instance of the same class
     * and if their serialize-representation of the Structured Field as a
     * textual HTTP field value is identical.
     */
    public function equals(mixed $other): bool;
}
