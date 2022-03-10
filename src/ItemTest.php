<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class ItemTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/boolean.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/number.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/number-generated.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/string.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/string-generated.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/item.json',
    ];

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_decimal_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDecimal(1_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_decimal_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDecimal(-1_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_instantiate_a_decimal(): void
    {
        self::assertSame('42.0', Item::fromDecimal(42)->toField());
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_integer_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromInteger(1_000_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_integer_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromInteger(-1_000_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_instantiates_an_integer(): void
    {
        self::assertSame('42', Item::fromInteger(42)->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_boolean(): void
    {
        self::assertSame('?1', Item::fromBoolean(true)->toField());
        self::assertSame('?0', Item::fromBoolean(false)->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_token(): void
    {
        self::assertSame('helloworld', Item::fromToken(new Token('helloworld'))->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_binary(): void
    {
        self::assertInstanceOf(ByteSequence::class, Item::fromByteSequence(ByteSequence::fromDecoded('foobar'))->value());
    }

    /**
     * @test
     */
    public function it_instantiates_a_string(): void
    {
        self::assertSame('"foobar"', Item::fromString('foobar')->toField());
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_an_invalid_string(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromString("\0foobar");
    }

    /**
     * @dataProvider itemTypeProvider
     * @test
     */
    public function it_can_tell_the_item_type(Item $item, string $expectedType): void
    {
        self::assertSame($expectedType === 'boolean', $item->isBoolean());
        self::assertSame($expectedType === 'integer', $item->isInteger());
        self::assertSame($expectedType === 'decimal', $item->isDecimal());
        self::assertSame($expectedType === 'string', $item->isString());
        self::assertSame($expectedType === 'token', $item->isToken());
        self::assertSame($expectedType === 'byte', $item->isByteSequence());
    }

    /**
     * @return iterable<string, array{item:Item, expectedType:string}>
     */
    public function itemTypeProvider(): iterable
    {
        return [
            'boolean' => [
                'item' => Item::fromBoolean(false),
                'expectedType' => 'boolean',
            ],
            'integer' => [
                'item' => Item::fromInteger(42),
                'expectedType' => 'integer',
            ],
            'decimal' => [
                'item' => Item::fromDecimal(42),
                'expectedType' => 'decimal',
            ],
            'string' => [
                'item' => Item::fromString('42'),
                'expectedType' => 'string',
            ],
            'token' => [
                'item' => Item::fromToken(new Token('forty-two')),
                'expectedType' => 'token',
            ],
            'byte' => [
                'item' => Item::fromByteSequence(ByteSequence::fromDecoded('😊')),
                'expectedType' => 'byte',
            ],
        ];
    }
}
