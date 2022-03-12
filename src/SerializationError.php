<?php

namespace Bakame\Http\StructuredFields;

use UnexpectedValueException;

final class SerializationError extends UnexpectedValueException implements StructuredFieldError
{
}
