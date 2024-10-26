<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use Bakame\Http\StructuredFields\StructuredFieldError;
use LogicException;

final class Violation extends LogicException implements StructuredFieldError
{
}
