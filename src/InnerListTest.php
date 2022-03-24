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
        self::assertFalse($instance->parameters->isEmpty());

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
        self::assertTrue($instance->parameters->isEmpty());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance->push('BarBaz');
        $instance->insert(1, );
        $member = $instance->get(1);
        self::assertCount(2, $instance);
        self::assertIsString($member->value);
        self::assertStringContainsString('BarBaz', $member->value);

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

        self::assertSame('bar', $instance->parameters->value('foo'));
    }

    /**
     * @test
     */
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $instance = InnerList::fromList([false], ['foo' => 'bar']);

        self::assertNull($instance->parameters->value('bar'));
    }

    /**
     * @test
     */
    public function it_successfully_parse_a_http_field(): void
    {
        $instance = InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("');

        self::assertCount(3, $instance);
        self::assertCount(1, $instance->parameters);
        self::assertSame('bar(', $instance->parameters->value('foo'));
        self::assertSame('hello)world', $instance->get(0)->value);
        self::assertSame(42, $instance->get(1)->value);
        self::assertSame(42.0, $instance->get(2)->value);
        self::assertInstanceOf(Token::class, $instance->get(2)->parameters->value('john'));
    }
}
