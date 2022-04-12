<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\TestCase;
use function var_export;

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
            ['a a'],
            ["a\u0001a"],
            ['3a'],
            ['a"a'],
            ['a,a'],
        ];
    }

    /**
     * @test
     */
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = Token::fromString('helloworld');

        /** @var Token $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
    }
}
