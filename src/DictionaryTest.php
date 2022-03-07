<?php

namespace Bakame\Http\StructuredField;

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

        self::assertSame($stringItem, $instance->findByIndex(0));
        self::assertSame($stringItem, $instance->findByKey('string'));
        self::assertNull($instance->findByKey('foobar'));
        self::assertNull($instance->findByIndex(42));
        self::assertTrue($instance->keyExists('string'));

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
        $instance->unset('boolean');

        self::assertCount(1, $instance);
        self::assertNull($instance->findByKey('boolean'));
        self::assertNull($instance->findByIndex(1));

        $instance->set('foobar', Item::fromString('BarBaz'));
        $foundItem =  $instance->findByIndex(1);

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

        new Dictionary(['bébé'=> Item::fromBoolean(false)]);
    }
}
