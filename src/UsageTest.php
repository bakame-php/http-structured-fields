<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class UsageTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/examples.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/key-generated.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/large-generated.json',
    ];
}
