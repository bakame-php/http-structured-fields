# The Item Data Type

This is the structure from which you will be able to access the actual field content.

## Items value

The eight (8) defined value types are all attached to an `Item` object where their value and
type are accessible using the following methods:

```php
use Bakame\Http\StructuredFields\Item;

$item = Item::fromHttpValue('@1234567890');
$item->type();  // return Type::Date;
$item->value()  // return the equivalent to DateTimeImmutable('@1234567890');
```

The `Item` value object exposes the following named constructors to instantiate
bare items (ie: item without parameters attached to them).

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

Item:new(DateTimeInterface|ByteSequence|Token|DisplayString|string|int|array|float|bool $value): self
Item:tryNew(mixed $value): ?self
Item::fromDecodedByteSequence(Stringable|string $value): self;
Item::fromEncodedDisplayString(Stringable|string $value): self;
Item::fromDecodedDisplayString(Stringable|string $value): self;
Item::fromEncodedByteSequence(Stringable|string $value): self;
Item::fromToken(Stringable|string $value): self;
Item::fromString(Stringable|string $value): self;
Item::fromDate(DateTimeInterface $datetime): self;
Item::fromDateFormat(string $format, string $datetime): self;
Item::fromDateString(string $datetime, DateTimeZone|string|null $timezone = null): self;
Item::fromTimestamp(int $value): self;
Item::fromDecimal(int|float $value): self;
Item::fromInteger(int|float $value): self;
Item::true(): self;
Item::false(): self;
```

To update the `Item` instance value, use the `withValue` method:

```php
use Bakame\Http\StructuredFields\Item;

Item::withValue(DateTimeInterface|ByteSequence|Token|DisplayString|string|int|float|bool $value): static
```

## Item Parameters

Items can have parameters attached to them. A parameter is a bere item, an item which can not have parameters
attach to it, to avoid recursive behaviour. Parameters are grouped in an ordered map container called `Parameters`.
They can be accessed by their indices **but also** by their required key attached to them.

```php

$item = Item::fromHttpValue('application/xml;q=0.9;foobar');
$item->value()->toString(); // returns 'application/xhtml+xml'
$item->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
$item->parameterByIndex(index: 1, default: ['toto', false]); // returns ['foobar', true] because there's a parameter at index 1
$item->parameters(); // returns a Parameters instance.
```

By default, you can access the member `Item` of a parameters using the following methods:

- `Item::parameters` returns a `Parameters` instance;
- `Item::parameterByKey` returns the value of the bare item instance attached to the supplied `key`;
- `Item::parameterByIndex` returns the value of the bare item instance attached to the supplied `index`;


## Item validation

### Validating the Item value

To validate the expected value of an `Item` you need to provide a callback to the `Item::value` method.
Let's say the field definition states that the value can only be a string or a token you can
translate that requirement as follows

```php

use Bakame\Http\StructuredFields\Type;

$field = Item::fromHttpValeue('bar;baz=42');

$value = $field->value(fn (mixed $value) => Type::fromVariable($value)->isOneOf(Type::Token, Type::String));
```

If the value is valid then it will populate the `$value` variable; otherwise an `Violation` exception will be thrown.

The exception will return a generic message. If you need to customize the message instead of returning `false` on
error, you can specify the template message to be used by the exception.

```php
use Bakame\Http\StructuredFields\Type;

$field = Item::fromHttpValeue('42.0;baz=42');

$value = $field
    ->value(
        fn (mixed $value) => match (true) {
            Type::fromVariable($value)->isOneOf(Type::Token, Type::String) => true,
            default => "The value '{value}' failed the RFC validation."
        }
    );
// the following exception will be thrown
// new Violation("The value '42.0' failed the RFC validation.");
```

As you can see not only did the generic message was changed, the exception also contains the serialized version
of the failed value.

### Validating a single Item parameter.

The `::parameterByKey` and `::parameterByIndex` methods can be used to validate a parameter value.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$field = Item::fromHttpValue('"Hello";baz=42;bar=toto');
$field->parameterByKey('bar'); 
// will return Token::fromString('toto');
$field->parameterByKey('bar', fn (mixed $value) => $value instanceof ByteSequence));
// will throw a generic exception message because the value is not a ByteSequence
```

Because parameters are optional by default you may also be able to specify a default value
or require the parameter presence. So the full validation for a single parameter defined by
its key can be done using the following code.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$field = Item::fromHttpValue('"Hello";baz=42;bar=toto');
$field->parameterByKey(
    key: 'bar', 
    validate: fn (mixed $value) => $value instanceof ByteSequence ? true : "The '{key}' parameter '{value}' is invalid",
    required: true,
    default: ByteSequence::fromDecoded('Hello world!'),
);
```

If you want to validate a parameter using its index instead, the method signature is the same but some
argument will have to be updated to accommodate index searching.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$field = Item::fromHttpValue('"Hello";baz=42;bar=toto');
$field->parameterByIndex(
    index: 1, 
    validate: fn (mixed $value, string $key) => $value instanceof ByteSequence ? true : "The  parameter '{key}' @t '{index}' whose value is '{value}' is invalid",
    required: true,
    default: ['foo', ByteSequence::fromDecoded('Hello world!')],
);
```

### Validating the complete Item

To completely filter an Item you need to:

- validate its value
- optionally validate each one of its parameters
- optionally validate the full parameters container if necessary

To do so the package provides 2 classes the `ParametersValidator` and the `ItemValidator`. Both classes are used to validate
in full an `Item`. Both classes expose a `validate` method which will return a `Result` instance containing the filtered data
in case of success or a non empty `ViolationList` class which contains all the `Validations` errors found.

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Validation\ItemValidator;
use Bakame\Http\StructuredFields\Validation\ParametersValidator;
use Bakame\Http\StructuredFields\Type;
use Bakame\Http\StructuredFields\Parameters;

$field = Item::fromHttpValue('"Hello";baz=42;bar="toto"');
$parametersValidator = ParametersValidator::new()
    ->filterByKeys([
        'bar' => [
            'validate' => fn (mixed $value) => Type::String->supports($value) ? true : "The '{key}' parameter '{value}' is invalid",
            'required' => true,
        ],
        'baz' => [
            'validate' => Type::Integer->supports(...),
            'required' => true,
        ],
        'foo' => [
            'validate' => Type::Token->supports(...),
            'default' => Token::fromString('hello'),
        ],
    ])
    ->filterByCriteria(fn (Parameters $parameters) => $parameters->isNotEmpty());
    
$itemValidator = ItemValidator::new()
    ->value(fn (mixed $value) => match (true) {
        Type::fromVariable($value)->isOneOf(Type::Token, Type::String) => true,
        default => "The value '{value}' failed the RFC validation."
    })
    ->parameters($parametersValidator);

$validation = $itemValidator->validate($field);
if ($validation->isFailed()) {
    throw $validation->toException(); 
    // throws a Violation exception whose error messages contains all the error messages found.
}

$itemValue = $validation->data->value;
$parameters = $validation->data->parameters->all();
echo $parameters['bar']; // returns 'toto';
echo $parameters['baz']; // returns 42
echo $parameters['foo']; // returns Token::fromString('hello');
```

The validation is deemed successfully if all the constraints are met successfully otherwise the validation failed.
When the validation fails only the `errors` readonly property of the `Result` class which represents a `ViolationList`
instance is filled; on success only the `data` readonly property of the `Result` class is.

In the Above example the `ParametersValidator::filterByKeys` is used but if the Item parameters are defined using their
indices you can choose to use the `ParametersValidator::filterByIndices` method instead. Its parameters are the same
only the default are changed to reflect the same difference seen between `parameterByKey` and `parameterByIndex`.

In the example we also use the `ParametersValidator::filterByCriteria` which expects a callback that will validate the
full `Parameters` container.

The 3 methods are all optional but at least one must be used otherwise a `Violation` exception will be thrown. Of note,
if only the `filterByCriteria` method is used, the full parameters values will be return otherwise only the filtered keys
or indices are returned.

The `ParametersValidator::filterByKeys` and `ParametersValidator::filterByIndices` methods are exclusive. If you use both
the last one called will be the one used during validation.

The `ParametersValidator` can be used independently to validate any `Parameters` instance.

you can do the following:

```php
$field = Item::fromHttpValue('"Hello";baz=42;bar="toto"');
$parameters = $field->parameters();
$validation = $parametersValidator->validate($parameters);
if ($validation->isFailed()) {
    throw $validation->toException(); 
    // throws a Violation exception whose error messages contains all the error messages found.
}

$parameters = $validation->data->all();
echo $parameters['bar']; // returns 'toto';
echo $parameters['baz']; // returns 42
echo $parameters['foo']; // returns Token::fromString('hello');
```

In this case the `data` property will directly contain the filtered parameters values.

Last but not least, there are other methods that can be used to validate the `Parameters` container, but we will see 
them when learning about ordered map containers in general.

&larr; [Types](03-value-types.md)  |  [Containers](05-containers.md) &rarr;
