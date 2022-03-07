<?php

/**
 * League.Period (https://github.com/bakame-php/http-sfv).
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bakame\Http\StructuredField;

interface SupportsParameters
{
    public function parameters(): Parameters;
}
