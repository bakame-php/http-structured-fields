<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Bakame\Http\StructuredFields\Validation\ErrorCode;
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
        $result = $this->validator->validate('foo');

        self::assertTrue($result->errors->hasErrors());
        self::assertCount(2, $result->errors);
        self::assertSame("The item value 'foo' failed validation.", $result->errors[ErrorCode::InvalidItemValue->value]->getMessage());
        self::assertSame('The item parameters constraints are missing.', $result->errors[ErrorCode::MissingParameterConstraints->value]->getMessage());
    }

    #[Test]
    public function it_will_succeed_and_return_the_item_value(): void
    {
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (mixed $value): bool => true)
            ->validate('foo');

        self::assertTrue($result->errors->hasNoError());
        self::assertEquals(Token::fromString('foo'), $result->value);
        self::assertSame([], $result->parameters);
    }

    #[Test]
    public function it_will_fail_validating_missing_parameters_when_the_item_has_some_parameters(): void
    {
        $item = Item::fromString('foo')->addParameter('foo', 'bar');
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(ErrorCode::list()))
            ->validate($item);

        self::assertTrue($result->errors->hasErrors());
        self::assertSame('foo', $result->value);
        self::assertSame([], $result->parameters);
        self::assertTrue($result->errors->has(ErrorCode::InvalidParametersValues->value));
    }

    #[Test]
    public function it_will_fail_validating_missing_parameters_when_the_item_has_no_parameters(): void
    {
        $item = Item::fromString('foo');
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(ErrorCode::list()))
            ->validate($item);

        self::assertTrue($result->errors->hasErrors());
        self::assertSame('foo', $result->value);
        self::assertSame([], $result->parameters);
        self::assertTrue($result->errors->has(ErrorCode::InvalidParametersValues->value));
    }

    #[Test]
    public function it_will_succeed_validating_allowed_parameters_and_returns_all_parameters_by_keys(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']))
            ->validate($item);

        self::assertFalse($result->errors->hasErrors());
        self::assertSame('foo', $result->value);
        self::assertSame(['foo' => 1, 'bar' => 2], $result->parameters);
    }

    #[Test]
    public function it_will_succeed_validating_allowed_parameters_and_returns_all_parameters_by_indices(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_INDICES)
            ->validate($item);

        self::assertFalse($result->errors->hasErrors());
        self::assertSame('foo', $result->value);
        self::assertSame([['foo', 1], ['bar', 2]], $result->parameters);
    }

    #[Test]
    public function it_will_fail_validating_parameters_by_keys(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_INDICES)
            ->parametersByKeys([
                'foo' => ['validate' => fn (mixed $value): bool => false],
                'bar' => ['validate' => fn (mixed $value): bool => true],
            ])
            ->validate($item);

        self::assertTrue($result->errors->hasErrors());
        self::assertSame('foo', $result->value);
        self::assertSame(['bar' => 2], $result->parameters);
        self::assertTrue($result->errors->has('foo'));
    }

    #[Test]
    public function it_will_succeed_validating_parameters_by_keys_and_override_parameters_validator_return(): void
    {
        $item = Item::fromPair(['foo', [['foo', 1], ['bar', 2]]]);
        $result = $this->validator
            ->value(fn (mixed $value): bool => true)
            ->parameters(fn (Parameters $parameters) => $parameters->allowedKeys(['foo', 'bar']), ItemValidator::USE_KEYS)
            ->parametersByIndices([
                1 => ['validate' => fn (mixed $value, string $key): bool => true],
            ])
            ->validate($item);

        self::assertTrue($result->errors->hasNoError());
        self::assertSame('foo', $result->value);
        self::assertSame([1 => ['bar', 2]], $result->parameters);
    }
}
