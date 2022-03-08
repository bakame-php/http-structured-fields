<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class DictionaryTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/dictionary.json',
    ];

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item_or_inner_list(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Dictionary($arrayParams);

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertSame($stringItem, $instance->getByKey('string'));
        self::assertTrue($instance->hasKey('string'));

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_elements(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::fromBoolean(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Dictionary($arrayParams);

        self::assertCount(2, $instance);
        self::assertFalse($instance->isEmpty());
        $instance->delete('boolean');

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasKey('boolean'));
        self::assertFalse($instance->hasIndex(1));

        $instance->append('foobar', Item::fromString('BarBaz'));
        $foundItem =  $instance->getByIndex(1);

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
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        new Dictionary(['bébé'=> Item::fromBoolean(false)]);
    }

    /**
     * @test
     */
    public function it_can_prepend_an_element(): void
    {
        $instance = new Dictionary();
        $instance->append('a', Item::fromBoolean(false));
        $instance->prepend('b', Item::fromBoolean(true));

        self::assertSame('b, a=?0', $instance->canonical());
    }

    /**
     * @test
     */
    public function it_can_returns_the_container_element_keys(): void
    {
        $instance = new Dictionary();
        self::assertSame([], $instance->keys());
        $instance->append('a', Item::fromBoolean(false));
        $instance->prepend('b', Item::fromBoolean(true));

        self::assertSame(['b', 'a'], $instance->keys());
    }
}
