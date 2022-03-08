<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

/**
 * @coversDefaultClass \Bakame\Http\StructuredField\OrderedList
 */
final class OrderedListTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/list.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/listlist.json',
    ];

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new OrderedList($arrayParams);

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertFalse($instance->isEmpty());

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_elements(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new OrderedList($arrayParams);

        self::assertCount(2, $instance);
        self::assertNotNull($instance->getByIndex(1));

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasIndex(1));

        $instance->push(Item::fromString('BarBaz'));
        $element = $instance->getByIndex(1);

        self::assertCount(2, $instance);
        self::assertInstanceOf(Item::class, $element);
        self::assertIsString($element->value());
        self::assertStringContainsString('BarBaz', $element->value());

        $instance->remove(0, 1);
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = new OrderedList();
        $container->unshift(Item::fromString('42'));
        $container->push(Item::fromInteger(42));
        $container->insert(1, Item::fromDecimal(42));
        $container->replace(0, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));

        self::assertFalse($container->hasKey('42'));
        self::assertCount(3, $container);
        self::assertFalse($container->isEmpty());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $container->canonical());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidIndex::class);

        $container = new OrderedList();
        $container->replace(0, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidIndex::class);

        $container = new OrderedList();
        $container->insert(3, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidIndex::class);

        $instance = new OrderedList();
        self::assertFalse($instance->hasKey('foobar'));

        $instance->getByKey('foobar');
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidIndex::class);

        $instance = new OrderedList();
        self::assertFalse($instance->hasIndex(3));

        $instance->getByIndex(3);
    }
}
