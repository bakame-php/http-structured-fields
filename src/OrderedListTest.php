<?php

namespace Bakame\Http\StructuredField;

/**
 * @coversDefaultClass \Bakame\Sfv\OrderedList
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

        self::assertSame($stringItem, $instance->findByIndex(0));
        self::assertNull($instance->findByKey('foobar'));
        self::assertNull($instance->findByIndex(42));
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
        self::assertNotNull($instance->findByIndex(1));

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertNull($instance->findByIndex(1));
        self::assertFalse($instance->indexExists(1));

        $instance->push(Item::fromString('BarBaz'));
        $element = $instance->findByIndex(1);

        self::assertCount(2, $instance);
        self::assertInstanceOf(Item::class, $element);
        self::assertIsString($element->value());
        self::assertStringContainsString('BarBaz', $element->value());

        $instance->remove(0, 1);
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }
}
