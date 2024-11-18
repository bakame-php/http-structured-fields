<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface StructuredFieldProvider
{
    /**
     * Returns ane of the StructuredField Data Type class.
     */
    public function toStructuredField(): OuterList|Dictionary|Item|InnerList|Parameters;
}
