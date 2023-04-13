<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;

interface ValueAccess extends StructuredField
{
    /**
     * Returns the underlying value.
     */
    public function value(): ByteSequence|Token|DateTimeImmutable|string|int|float|bool;

    /**
     * Returns the value type.
     */
    public function type(): Type;

    /**
     * Returns a new instance with the newly associated value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified value change.
     *
     * @throws SyntaxError If the value is invalid or not supported
     */
    public function withValue(DateTimeInterface|ByteSequence|Token|string|int|float|bool $value): static;
}
