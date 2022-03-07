<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

/**
 * @coversDefaultClass \Bakame\Sfv\Parameters
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

        self::assertSame($stringItem, $instance->findByIndex(0));
        self::assertSame($stringItem, $instance->findByKey('string'));
        self::assertNull($instance->findByKey('foobar'));
        self::assertNull($instance->findByIndex(42));
        self::assertTrue($instance->indexExist('string'));

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

        $instance->unset('boolean');

        self::assertCount(1, $instance);
        self::assertNull($instance->findByKey('boolean'));
        self::assertNull($instance->findByIndex(1));

        $instance->set('foobar', Item::fromString('BarBaz'));
        $foundItem =  $instance->findByIndex(1);

        self::assertCount(2, $instance);
        self::assertNotNull($instance->findByKey('foobar'));
        self::assertInstanceOf(Item::class, $foundItem);
        self::assertIsString($foundItem->value());
        self::assertStringContainsString('BarBaz', $foundItem->value());

        $instance->unset('foobar', 'string');
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
}
