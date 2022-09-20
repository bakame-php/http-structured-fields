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
        __DIR__.'/../vendor/httpwg/structured-field-tests/token.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/token-generated.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/item.json',
    ];

    /** @test */
    public function it_fails_to_instantiate_a_decimal_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(1_000_000_000_000.0);
    }

    /** @test */
    public function it_fails_to_instantiate_a_decimal_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(-1_000_000_000_000.0);
    }

    /** @test */
    public function it_instantiate_a_decimal(): void
    {
        self::assertSame('42.0', Item::from(42.0)->toHttpValue());
    }

    /** @test */
    public function it_fails_to_instantiate_a_integer_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(1_000_000_000_000_000);
    }

    /** @test */
    public function it_fails_to_instantiate_a_integer_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(-1_000_000_000_000_000);
    }

    /** @test */
    public function it_instantiates_an_integer(): void
    {
        self::assertSame('42', Item::from(42)->toHttpValue());
    }

    /** @test */
    public function it_instantiates_a_boolean(): void
    {
        self::assertSame('?1', Item::from(true)->toHttpValue());
        self::assertSame('?0', Item::from(false)->toHttpValue());
    }

    /** @test */
    public function it_instantiates_a_token(): void
    {
        self::assertSame('helloworld', Item::from(Token::fromString('helloworld'))->toHttpValue());
        self::assertSame('helloworld', Item::fromToken('helloworld')->toHttpValue());
    }

    /** @test */
    public function it_instantiates_a_binary(): void
    {
        self::assertSame('foobar', Item::from(ByteSequence::fromDecoded('foobar'))->value());
        self::assertSame('foobar', Item::fromDecodedByteSequence('foobar')->value());
        self::assertSame('foobar', Item::fromEncodedByteSequence('Zm9vYmFy')->value());
    }

    /** @test */
    public function it_instantiates_a_string(): void
    {
        self::assertSame('"foobar"', Item::from('foobar')->toHttpValue());
    }

    /** @test */
    public function it_fails_to_instantiate_an_invalid_string(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from("\0foobar");
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
                'item' => Item::from(false),
                'expectedType' => 'boolean',
            ],
            'integer' => [
                'item' => Item::from(42),
                'expectedType' => 'integer',
            ],
            'decimal' => [
                'item' => Item::from(42.0),
                'expectedType' => 'decimal',
            ],
            'string' => [
                'item' => Item::from('42'),
                'expectedType' => 'string',
            ],
            'token' => [
                'item' => Item::from(Token::fromString('forty-two')),
                'expectedType' => 'token',
            ],
            'byte' => [
                'item' => Item::from(ByteSequence::fromDecoded('😊')),
                'expectedType' => 'byte',
            ],
        ];
    }

    /** @test */
    public function test_in_can_be_instantiated_using_bare_items(): void
    {
        $item1 = Item::from('/terms', [
            'string' => '42',
            'integer' => 42,
            'float' => 4.2,
            'boolean' => true,
            'token' => Token::fromString('forty-two'),
            'byte-sequence' => ByteSequence::fromDecoded('a42'),
        ]);

        $item2 = Item::from('/terms', new ArrayObject([
            'string' => Item::from('42'),
            'integer' => Item::from(42),
            'float' => Item::from(4.2),
            'boolean' => Item::from(true),
            'token' => Item::from(Token::fromString('forty-two')),
            'byte-sequence' => Item::from(ByteSequence::fromDecoded('a42')),
        ]));

        self::assertEquals($item2, $item1);
    }

    /** @test */
    public function it_will_fail_with_wrong_token(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromHttpValue('foo,bar;a=3');
    }

    /** @test */
    public function it_can_access_its_parameter_values(): void
    {
        $instance = Item::fromHttpValue('1; a; b=?0');

        self::assertTrue($instance->parameters()['a']->value());
    }

    /** @test */
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(StructuredFieldError::class);

        $instance = Item::fromHttpValue('1; a; b=?0');
        $instance->parameters()['bar']->value();
    }

    /** @test */
    public function it_can_exchange_parameters(): void
    {
        $instance = Item::from(Token::fromString('babayaga'));

        self::assertCount(0, $instance->parameters());

        $parameters = $instance->parameters();
        $parameters->clear();
        $parameters->mergeAssociative(['foo' => 'bar']);

        self::assertCount(0, $instance->parameters());
        self::assertSame('bar', $parameters['foo']->value());
    }

    /** @test */
    public function it_can_create_an_item_from_a_array_of_pairs(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'));
        $instance2 = Item::fromPair([Token::fromString('babayaga')]);
        $instance3 = Item::fromPair([Token::fromString('babayaga'), []]);

        self::assertEquals($instance2, $instance1);
        self::assertEquals($instance3, $instance1);
    }

    /**
     * @test
     * @dataProvider invalidPairProvider
     * @param array<mixed> $pair
     */
    public function it_fails_to_create_an_item_from_an_array_of_pairs(array $pair): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromPair($pair);  /* @phpstan-ignore-line */
    }

    /**
     * @return iterable<string, array{pair:array<mixed>}>
     */
    public function invalidPairProvider(): iterable
    {
        yield 'empty pair' => ['pair' => []];
        yield 'empty extra filled pair' => ['pair' => [1, [2], 3]];
        yield 'associative array' => ['pair' => ['value' => 'bar', 'parameters' => ['foo' => 'bar']]];
    }

    /** @test */
    public function it_can_create_an_item_from_a_array_of_pairs_and_parameters(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = Item::fromPair([Token::fromString('babayaga'), [['a', true]]]);

        self::assertEquals($instance2, $instance1);
    }

    /** @test */
    public function it_can_create_via_with_value_method_a_new_object(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withValue(Token::fromString('babayaga'));
        $instance3 = $instance1->withValue('babayaga');

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1, $instance3);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals($instance1->parameters(), $instance3->parameters());
    }

    /** @test */
    public function it_can_create_via_with_parameters_method_a_new_object(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withParameters(Parameters::fromAssociative(['a' => true]));
        $instance3 = $instance1->withParameters(Parameters::fromAssociative(['a' => false]));

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
    }
}
