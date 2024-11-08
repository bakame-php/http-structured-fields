# Validation

The package also can help with validating your field. If we go back to our example about the permission policy.
We assumed that we indeed parsed a valid field but nothing can prevent us from parsing a completely unrelated
field also defined as a dictionary field and pretend it to be a permission policy field.

A way to prevent that is to add simple validation rules on the field value or structure.

## Validating a Bare Item.

To validate the expected value of an `Item` you need to provide a callback to the `Item::value` method.
Let's say the RFC says that the value can only be a string or a token you can translate that requiremebt as follow

```php

use Bakame\Http\StructuredFields\Type;

$value = Item::fromString('42')->value(is_string(...));
```

If the value is valid then it will populate the `$value` variable; otherwise an `Violation` exception will be thrown.

The exception will return a generic message. If you need to customize the message instead of returning `false` on
error, you can specify the template message to be used by the exception.

```php
use Bakame\Http\StructuredFields\Type;

$value = Item::fromDecimal(42)
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

## Validating a single Parameter.

The same logic can be applied .when validating a parameter value.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$parameters = Parameters::fromHttpValue(';baz=42;bar=toto');
$parameters->valueByKey('bar'); 
// will return Token::fromString('toto');
$parameters->valueByKey('bar', fn (mixed $value) => $value instanceof ByteSequence));
// will throw a generic exception message because the value is not a ByteSequence
```

Because parameters are optional by default you may also be able to specify a default value
or require the parameter presence. So the full validation for a single parameter defined by
its key can be done using the following code.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$parameters = Parameters::fromHttpValue(';baz=42;bar=toto');
$parameters->valueByKey(
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

$parameters = Parameters::fromHttpValue(';baz=42;bar=toto');
$parameters->valueByIndex(
    index: 1, 
    validate: fn (mixed $value, string $key) => $value instanceof ByteSequence ? true : "The  parameter '{key}' @t '{index}' whose value is '{value}' is invalid",
    required: true,
    default: ['foo', ByteSequence::fromDecoded('Hello world!')],
);
```

## Validating the Parameters container.

The most common use case of parameters involve more than one parameter to validate. Imagine we have to validate
the cookie field. It will contain more than one parameter so instead of comparing each parameter separately the
package allows validating multiple parameters at the same time using the `Parameters::validateByKeys` and its
couterpart `Parameters::validateByIndices.`

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Parameters;

$parameters = Parameters::fromHttpValue(';baz=42;bar=toto')->validateByKeys([
    [
        'bar' => [
            'validate' => fn (mixed $value) => $value instanceof ByteSequence ? true : "The '{key}' parameter '{value}' is invalid",
            'required' => true,
            'default' => ByteSequence::fromDecoded('Hello world!'),
        ],
         ...
]);
```

The returned value contains the validated parametes as well as a `ViolationList` object which contains all the violations
found if any.


&larr; [Type](04-types.md)
