<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use OutOfBoundsException;

class InvalidIndex extends OutOfBoundsException implements StructuredFieldError
{
}
