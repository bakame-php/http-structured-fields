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

    /**
     * @test
     */
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = new Token('helloworld');

        /** @var Token $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
    }
}
