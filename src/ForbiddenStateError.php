<?php

namespace Bakame\Http\StructuredFields;

use LogicException;

final class ForbiddenStateError extends LogicException implements StructuredFieldError
{
}
