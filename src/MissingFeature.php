<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class MissingFeature extends SyntaxError
{
    public static function dueToLackOfSupport(Type $type, Ietf $rfc): self
    {
        return new self('The \''.$type->value.'\' type is not handled by '.strtoupper($rfc->name));
    }
}
