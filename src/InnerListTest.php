<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\TestCase;
use function iterator_to_array;
use function var_export;

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
        $instance = InnerList::fromList($arrayParams, Parameters::fromAssociative(['test' => Item::from(42)]));
        self::assertFalse($instance->parameters()->isEmpty());

        self::assertSame($stringItem, $instance->get(0));
        self::assertFalse($instance->isEmpty());

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
        $instance->clear();
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = InnerList::fromList($arrayParams);

        self::assertCount(2, $instance);
        self::assertTrue($instance->has(1));
        self::assertTrue($instance->parameters()->isEmpty());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance->push('BarBaz');
        $instance->insert(1, );
        $member = $instance->get(1);
        self::assertCount(2, $instance);
        self::assertIsString($member->value());
        self::assertStringContainsString('BarBaz', $member->value());

        $instance->remove(0, 1);
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = InnerList::fromList();
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

        $container = InnerList::from();
        $container->replace(0, ByteSequence::fromDecoded('Hello World'));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = InnerList::from();
        $container->insert(3, ByteSequence::fromDecoded('Hello World'));
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = InnerList::fromList();
        self::assertFalse($instance->has(3));

        $instance->get(3);
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = InnerList::fromList([false], ['foo' => 'bar']);
        $instance2 = InnerList::from(true);
        $instance3 = InnerList::fromList([42], ['foo' => 'baz']);
        $expected = InnerList::fromList([false, true, 42], ['foo' => 'baz']);

        $instance1->merge($instance2, $instance3);

        self::assertCount(3, $instance1);
        self::assertSame($expected->toHttpValue(), $instance1->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = InnerList::from(false);
        $instance->merge();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = InnerList::fromList([false], ['foo' => 'bar']);
        $instance2 = InnerList::from(true);
        $instance3 = InnerList::fromList([42], ['foo' => 'baz']);
        $expected = InnerList::fromList([42, true, false], ['foo' => 'bar']);

        $instance3->merge($instance2, $instance1);

        self::assertCount(3, $instance3);
        self::assertSame($expected->toHttpValue(), $instance3->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = InnerList::fromList([false], ['foo' => 'bar']);

        /** @var InnerList $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
    }

    /**
     * @test
     */
    public function it_can_access_its_parameter_values(): void
    {
        $instance = InnerList::fromList([false], ['foo' => 'bar']);

        self::assertSame('bar', $instance->parameter('foo'));
    }

    /**
     * @test
     */
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = InnerList::fromList([false], ['foo' => 'bar']);
        $instance->parameter('bar');
    }

    /**
     * @test
     */
    public function it_can_exchange_parameters(): void
    {
        $instance = InnerList::from(false, true, 42, 'forty-two');

        self::assertCount(0, $instance->parameters());

        $instance->exchangeParameters(['foo' => 'bar']);

        self::assertCount(1, $instance->parameters());
        self::assertSame('bar', $instance->parameter('foo'));
    }
}
