<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;

final class ForbiddenOperation extends LogicException implements StructuredFieldError
{
}
