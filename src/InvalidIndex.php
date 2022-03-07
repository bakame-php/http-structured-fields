<?php

namespace Bakame\Http\StructuredField;

use OutOfBoundsException;

class InvalidIndex extends OutOfBoundsException implements StructuredFieldError
{
}
