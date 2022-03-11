<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\InnerList
 */
final class InnerListTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::fromType('helloWorld');
        $booleanItem = Item::fromType(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new InnerList($arrayParams, new Parameters(['test' => Item::fromType(42)]));
        self::assertFalse($instance->parameters()->isEmpty());

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertFalse($instance->isEmpty());

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_elements(): void
    {
        $stringItem = Item::fromType('helloWorld');
        $booleanItem = Item::fromType(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new InnerList($arrayParams);

        self::assertCount(2, $instance);
        self::assertNotNull($instance->getByIndex(1));
        self::assertTrue($instance->hasIndex(1));
        self::assertTrue($instance->parameters()->isEmpty());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasIndex(1));

        $instance->push(Item::fromType('BarBaz'));
        $instance->insert(1, );
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
        $container->unshift(Item::fromType('42'));
        $container->push(Item::fromType(42));
        $container->insert(1, Item::fromType(42.0));
        $container->replace(0, Item::fromType(ByteSequence::fromDecoded('Hello World')));

        self::assertFalse($container->hasKey('42'));
        self::assertCount(3, $container);
        self::assertFalse($container->isEmpty());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', $container->toField());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = new InnerList();
        $container->replace(0, Item::fromType(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = new InnerList();
        $container->insert(3, Item::fromType(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new InnerList();
        self::assertFalse($instance->hasKey('foobar'));

        $instance->getByKey('foobar');
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new InnerList();
        self::assertFalse($instance->hasIndex(3));

        $instance->getByIndex(3);
    }

    /**
     * @test
     */
    public function it_can_returns_the_container_element_keys(): void
    {
        $instance = new InnerList();
        self::assertSame([], $instance->keys());

        $instance->push(Item::fromType(false), Item::fromType(true));
        self::assertSame([], $instance->keys());
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = new InnerList([Item::fromType(false)]);
        $instance2 = new InnerList([Item::fromType(true)]);
        $instance3 = new InnerList([Item::fromType(42)]);
        $expected = new InnerList([Item::fromType(false), Item::fromType(true), Item::fromType(42)]);

        $instance1->merge($instance2, $instance3);

        self::assertCount(3, $instance1);
        self::assertSame($expected->toField(), $instance1->toField());
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = new InnerList([Item::fromType(false)]);
        $instance->merge();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = new InnerList([Item::fromType(false)]);
        $instance2 = new InnerList([Item::fromType(true)]);
        $instance3 = new InnerList([Item::fromType(42)]);
        $expected = new InnerList([Item::fromType(42), Item::fromType(true), Item::fromType(false)]);

        $instance3->merge($instance2, $instance1);

        self::assertCount(3, $instance3);
        self::assertSame($expected->toField(), $instance3->toField());
    }
}
