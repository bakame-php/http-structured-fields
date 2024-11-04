<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use InvalidArgumentException;

class SyntaxError extends InvalidArgumentException implements StructuredFieldError
{
}
