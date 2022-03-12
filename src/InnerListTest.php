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
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new InnerList($arrayParams, new Parameters(['test' => Item::from(42)]));
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
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = new InnerList($arrayParams);

        self::assertCount(2, $instance);
        self::assertNotNull($instance->getByIndex(1));
        self::assertTrue($instance->hasIndex(1));
        self::assertTrue($instance->parameters()->isEmpty());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasIndex(1));

        $instance->push('BarBaz');
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

        InnerList::fromHttpValue('(1 42)foobar');
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = new InnerList();
        $container->unshift('42');
        $container->push(42);
        $container->insert(1, 42.0);
        $container->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertCount(3, $container);
        self::assertFalse($container->isEmpty());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', $container->toHttpValue());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = new InnerList();
        $container->replace(0, ByteSequence::fromDecoded('Hello World'));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = new InnerList();
        $container->insert(3, ByteSequence::fromDecoded('Hello World'));
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
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = new InnerList([false], ['foo' => 'bar']);
        $instance2 = new InnerList([true]);
        $instance3 = new InnerList([42], ['foo' => 'baz']);
        $expected = new InnerList([false, true, 42], ['foo' => 'baz']);

        $instance1->merge($instance2, $instance3);

        self::assertCount(3, $instance1);
        self::assertSame($expected->toHttpValue(), $instance1->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = new InnerList([false]);
        $instance->merge();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = new InnerList([false], ['foo' => 'bar']);
        $instance2 = new InnerList([true]);
        $instance3 = new InnerList([42], ['foo' => 'baz']);
        $expected = new InnerList([42, true, false], ['foo' => 'bar']);

        $instance3->merge($instance2, $instance1);

        self::assertCount(3, $instance3);
        self::assertSame($expected->toHttpValue(), $instance3->toHttpValue());
    }
}
