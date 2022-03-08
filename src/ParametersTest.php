<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

/**
 * @coversDefaultClass \Bakame\Http\StructuredField\Parameters
 */
final class ParametersTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-dict.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-list.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-listlist.json',
    ];

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Parameters($arrayParams);

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertSame($stringItem, $instance->getByKey('string'));
        self::assertNull($instance->getByKey('foobar'));
        self::assertNull($instance->getByIndex(42));
        self::assertTrue($instance->hasKey('string'));

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_with_an_item_containing_already_parameters(): void
    {
        $this->expectException(SyntaxError::class);

        new Parameters([
            'foo' => Item::fromBoolean(
                true,
                new Parameters(['bar' => Item::fromBoolean(false)])
            ),
        ]);
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_elements(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Parameters($arrayParams);

        self::assertCount(2, $instance);

        $instance->delete('boolean');

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasKey('boolean'));
        self::assertFalse($instance->hasIndex(1));

        $instance->append('foobar', Item::fromString('BarBaz'));
        $foundItem =  $instance->getByIndex(1);

        self::assertCount(2, $instance);
        self::assertInstanceOf(Item::class, $foundItem);
        self::assertIsString($foundItem->value());
        self::assertStringContainsString('BarBaz', $foundItem->value());

        $instance->delete('foobar', 'string');
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        new Parameters(['bébé'=> Item::fromBoolean(false)]);
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new Dictionary();
        self::assertFalse($instance->hasKey('foobar'));

        $instance->getByKey('foobar');
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new Dictionary();
        self::assertFalse($instance->hasIndex(3));

        $instance->getByIndex(3);
    }

    /**
     * @test
     */
    public function it_can_prepend_an_element(): void
    {
        $instance = new Parameters();
        $instance->append('a', Item::fromBoolean(false));
        $instance->prepend('b', Item::fromBoolean(true));

        self::assertSame(';b;a=?0', $instance->canonical());
    }

    /**
     * @test
     */
    public function it_can_returns_the_container_element_keys(): void
    {
        $instance = new Parameters();
        self::assertSame([], $instance->keys());
        $instance->append('a', Item::fromBoolean(false));
        $instance->prepend('b', Item::fromBoolean(true));

        self::assertSame(['b', 'a'], $instance->keys());
    }
}
