<?php

namespace Bakame\Http\StructuredField;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Sfv\InnerList
 */
final class InnerListTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new InnerList($arrayParams, new Parameters(['test' => Item::fromInteger(42)]));
        self::assertFalse($instance->parameters()->isEmpty());

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
        $instance = new InnerList($arrayParams);

        self::assertCount(2, $instance);
        self::assertNotNull($instance->findByIndex(1));
        self::assertTrue($instance->indexExists(1));
        self::assertTrue($instance->parameters()->isEmpty());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertNull($instance->findByIndex(1));
        self::assertFalse($instance->indexExists(1));

        $instance->push(Item::fromString('BarBaz'));
        $instance->insert(1, );
        $element = $instance->findByIndex(1);
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
    public function it_fails_to_instantiate_with_wrong_parameters_in_field(): void
    {
        $this->expectException(SyntaxError::class);

        InnerList::fromField('(1 42)foobar');
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = new InnerList();
        $container->unshift(Item::fromString('42'));
        $container->push(Item::fromInteger(42));
        $container->insert(1, Item::fromDecimal(42));
        $container->replace(0, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $container);
        self::assertFalse($container->isEmpty());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', $container->canonical());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidIndex::class);

        $container = new InnerList();
        $container->replace(0, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidIndex::class);

        $container = new InnerList();
        $container->insert(3, Item::fromByteSequence(ByteSequence::fromDecoded('Hello World')));
    }
}
