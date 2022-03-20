<?php

namespace Bakame\Http\StructuredFields;

use UnexpectedValueException;

final class ForbiddenStateError extends UnexpectedValueException implements StructuredFieldError
{
}
