<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\Token
 */
final class TokenTest extends TestCase
{
    /**
     * @test
     * @dataProvider invalidTokenString
     */
    public function it_will_fail_on_invalid_token_string(string $httpValue): void
    {
        $this->expectException(SyntaxError::class);

        Token::fromString($httpValue);
    }

    /**
     * @return array<array{0:string}>
     */
    public function invalidTokenString(): array
    {
        return [
            'token contains spaces inside' => ['a a'],
            'token contains non-ASCII characters' => ["a\u0001a"],
            'token starts with invalid characters' => ['3a'],
            'token contains double quote' => ['a"a'],
            'token contains comma' => ['a,a'],
        ];
    }
}
