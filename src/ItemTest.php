<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayObject;

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

        Item::fromType(1_000_000_000_000.0);
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_decimal_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromType(-1_000_000_000_000.0);
    }

    /**
     * @test
     */
    public function it_instantiate_a_decimal(): void
    {
        self::assertSame('42.0', Item::fromType(42.0)->toField());
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_integer_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromType(1_000_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_a_integer_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromType(-1_000_000_000_000_000);
    }

    /**
     * @test
     */
    public function it_instantiates_an_integer(): void
    {
        self::assertSame('42', Item::fromType(42)->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_boolean(): void
    {
        self::assertSame('?1', Item::fromType(true)->toField());
        self::assertSame('?0', Item::fromType(false)->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_token(): void
    {
        self::assertSame('helloworld', Item::fromType(new Token('helloworld'))->toField());
    }

    /**
     * @test
     */
    public function it_instantiates_a_binary(): void
    {
        self::assertInstanceOf(ByteSequence::class, Item::fromType(ByteSequence::fromDecoded('foobar'))->value());
    }

    /**
     * @test
     */
    public function it_instantiates_a_string(): void
    {
        self::assertSame('"foobar"', Item::fromType('foobar')->toField());
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_an_invalid_string(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromType("\0foobar");
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
                'item' => Item::fromType(false),
                'expectedType' => 'boolean',
            ],
            'integer' => [
                'item' => Item::fromType(42),
                'expectedType' => 'integer',
            ],
            'decimal' => [
                'item' => Item::fromType(42.0),
                'expectedType' => 'decimal',
            ],
            'string' => [
                'item' => Item::fromType('42'),
                'expectedType' => 'string',
            ],
            'token' => [
                'item' => Item::fromType(new Token('forty-two')),
                'expectedType' => 'token',
            ],
            'byte' => [
                'item' => Item::fromType(ByteSequence::fromDecoded('😊')),
                'expectedType' => 'byte',
            ],
        ];
    }

    /**
     * @test
     */
    public function test_in_can_be_instantiated_using_bare_items(): void
    {
        $item1 = Item::fromType('/terms', [
            'string' => '42',
            'integer' => 42,
            'float' => 4.2,
            'boolean' => true,
            'token' => new Token('forty-two'),
            'byte-sequence' => ByteSequence::fromDecoded('a42'),
        ]);

        $item2 = Item::fromType('/terms', new ArrayObject([
            'string' => Item::fromType('42'),
            'integer' => Item::fromType(42),
            'float' => Item::fromType(4.2),
            'boolean' => Item::fromType(true),
            'token' => Item::fromType(new Token('forty-two')),
            'byte-sequence' => Item::fromType(ByteSequence::fromDecoded('a42')),
        ]));

        self::assertEquals($item2, $item1);
    }
}
