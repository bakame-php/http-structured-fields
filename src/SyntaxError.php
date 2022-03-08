<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use InvalidArgumentException;

final class SyntaxError extends InvalidArgumentException implements StructuredFieldError
{
}
