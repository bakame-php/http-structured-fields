<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Http\StructuredField\Token
 */
final class TokenTest extends TestCase
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/token.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/token-generated.json',
    ];

    /**
     * @test
     */
    public function it_will_fail_on_invalid_token_string(): void
    {
        $this->expectException(SyntaxError::class);

        new Token('a a');
    }
}
