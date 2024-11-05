<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Bakame\Http\StructuredFields\Validation\ErrorCode;
use Bakame\Http\StructuredFields\Validation\ProcessedItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ItemValidatorTest extends TestCase
{
    private ItemValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = ItemValidator::new();
    }

    #[Test]
    public function it_will_fail_item_value_validation_by_default(): void
    {
        $validation = $this->validator->validate('foo');

        self::assertTrue($validation->isFailed());
        self::assertCount(2, $validation->errors);
        self::assertSame("The item value 'foo' failed validation.", $validation->errors[ErrorCode::InvalidItemValue->value]->getMessage());
        self::assertSame('The item parameters constraints are missing.', $validation->errors[ErrorCode::MissingParameterConstraints->value]->getMessage());
    }

    #[Test]
    public function it_will_succeed_and_return_the_item_value(): void
    {
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (mixed $value): bool => true)
            ->validate('foo');

        self::assertTrue($validation->isSuccess());
        self::assertInstanceOf(ProcessedItem::class, $validation->data);
        self::assertEquals(Token::fromString('foo'), $validation->data->value);
        self::assertSame([], iterator_to_array($validation->data->parameters, true));
    }

    #[Test]
    public function it_will_fail_validating_missing_parameters_when_the_item_has_some_parameters(): void
    {
        $item = Item::fromString('foo')->addParameter('foo', 'bar');
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(ErrorCode::list()))
            ->validate($item);

        self::assertTrue($validation->isFailed());
        self::assertNull($validation->data);
        self::assertTrue($validation->errors->has(ErrorCode::InvalidParametersValues->value));
    }

    #[Test]
    public function it_will_successfully_validating_missing_parameters_when_the_item_has_no_parameters(): void
    {
        $item = Item::fromString('foo');
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(ErrorCode::list()))
            ->validate($item);

        self::assertTrue($validation->isSuccess());
    }

    #[Test]
    public function it_will_succeed_validating_allowed_parameters_and_returns_all_parameters_by_keys(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']))
            ->validate($item);

        self::assertFalse($validation->isFailed());
        self::assertInstanceOf(ProcessedItem::class, $validation->data);
        self::assertSame('foo', $validation->data->value);
        self::assertSame(['foo' => 1, 'bar' => 2], iterator_to_array($validation->data->parameters, true));
    }

    #[Test]
    public function it_will_succeed_validating_allowed_parameters_and_returns_all_parameters_by_indices(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_INDICES)
            ->validate($item);

        self::assertTrue($validation->isSuccess());
        self::assertInstanceOf(ProcessedItem::class, $validation->data);
        self::assertSame('foo', $validation->data->value);
        self::assertSame([['foo', 1], ['bar', 2]], iterator_to_array($validation->data->parameters, true));
    }

    #[Test]
    public function it_will_fail_validating_parameters_by_keys(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_INDICES)
            ->parametersByKeys([
                'foo' => ['validate' => fn (mixed $value): bool => false],
                'bar' => ['validate' => fn (mixed $value): bool => true],
            ])
            ->validate($item);

        self::assertTrue($validation->isFailed());
        self::assertNull($validation->data);
        self::assertTrue($validation->errors->has('foo'));
    }

    #[Test]
    public function it_will_succeed_validating_parameters_by_keys_and_override_parameters_validator_return(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $validation = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_KEYS)
            ->parametersByIndices([
                1 => ['validate' => fn (mixed $value, string $key): bool => true],
            ])
            ->validate($item);

        self::assertTrue($validation->isSuccess());
        self::assertInstanceOf(ProcessedItem::class, $validation->data);
        self::assertSame('foo', $validation->data->value);
        self::assertSame([1 => ['bar', 2]], iterator_to_array($validation->data->parameters, true));
    }
}
