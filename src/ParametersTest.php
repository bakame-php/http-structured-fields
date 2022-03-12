<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\Parameters
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
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Parameters($arrayParams);

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertSame($stringItem, $instance->getByKey('string'));
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
            'foo' => Item::from(
                true,
                new Parameters(['bar' => Item::from(false)])
            ),
        ]);
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_elements(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = new Parameters($arrayParams);

        self::assertCount(2, $instance);

        $instance->delete('boolean');

        self::assertCount(1, $instance);
        self::assertFalse($instance->hasKey('boolean'));
        self::assertFalse($instance->hasIndex(1));

        $instance->append('foobar', Item::from('BarBaz'));
        $foundItem =  $instance->getByIndex(1);

        self::assertCount(2, $instance);
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

        new Parameters(['bébé'=> Item::from(false)]);
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new Parameters();
        self::assertFalse($instance->hasKey('foobar'));

        $instance->getByKey('foobar');
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = new Parameters();
        self::assertFalse($instance->hasIndex(3));

        $instance->getByIndex(3);
    }

    /**
     * @test
     */
    public function it_can_prepend_an_element(): void
    {
        $instance = new Parameters();
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame(';b;a=?0', $instance->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_returns_the_container_element_keys(): void
    {
        $instance = new Parameters();
        self::assertSame([], $instance->keys());
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame(['b', 'a'], $instance->keys());
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = new Parameters(['a' =>false]);
        $instance2 = new Parameters(['b' => true]);
        $instance3 = new Parameters(['a' => 42]);

        $instance1->merge($instance2, $instance3);

        self::assertEquals(Item::from(42), $instance1->getByKey('a'));
        self::assertEquals(Item::from(true), $instance1->getByKey('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_dictionaries_different_result(): void
    {
        $instance1 = new Parameters(['a' => Item::from(false)]);
        $instance2 = new Parameters(['b' => Item::from(true)]);
        $instance3 = new Parameters(['a' => Item::from(42)]);

        $instance3->merge($instance2, $instance1);

        self::assertEquals(Item::from(false), $instance3->getByKey('a'));
        self::assertEquals(Item::from(true), $instance3->getByKey('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = new Parameters(['a' => Item::from(false)]);
        $instance->merge();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_fails_if_internal_parameters_are_changed_illegally(): void
    {
        $this->expectException(SyntaxError::class);

        $fields = Item::from('/terms', ['rel' => 'copyright', 'anchor' => '#foo']);
        $fields->parameters()->getByKey('anchor')->parameters()->set('yolo', 42);
        $fields->toHttpValue();
    }
}
