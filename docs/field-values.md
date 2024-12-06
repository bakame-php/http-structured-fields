---
title: Accessing the HTTP field values
order: 4
---

# HTTP Fields Values

## Value type conversion to PHP

The RFC defines several value types that the package either convert to PHP native type whenever possible
or provides a class based alternative. The table below summarizes the value type system.

| RFC Type      | PHP Type                  | Package Enum Name     | Package Enum Value | RFC min. version |
|---------------|---------------------------|-----------------------|--------------------|------------------|
| Integer       | `int`                     | `Type::Integer`       | `ìnteger`          | RFC8941          |
| Decimal       | `float`                   | `Type::Decimal`       | `decimal`          | RFC8941          |
| String        | `string`                  | `Type::String`        | `string`           | RFC8941          |
| Boolean       | `bool`                    | `Type::Boolean`       | `boolean`          | RFC8941          |
| Token         | class `Token`             | `Type::Token`         | `token`            | RFC8941          |
| Byte Sequence | class `Bytes`             | `Type::Bytes`         | `binary`           | RFC8941          |
| Date          | class `DateTimeImmutable` | `Type::Date`          | `date`             | RFC9651          |
| DisplayString | class `DisplayString`     | `Type::DisplayString` | `displaystring`    | RFC9651          |

> [!WARNING]
> The translation to PHP native type does not mean that all PHP values are usable. For instance, in the
> following example, what is considered to be a valid string in PHP is not considered as compliant
> to the string type according to the RFC.

```php
Item::fromString("https://a.bébé.com");
 // will trigger a SyntaxError because a
 // structured field string type can not
 // contain UTF-8 characters
```

> [!NOTE]
> The `Date` and `DisplayString` types were added in the accepted RFC9651 
> but are not part of the obsolete RFC8941 specification.

The Enum `Type` list all available types and can be used to determine the RFC type
corresponding to a PHP structure using the `Type::fromVariable` static method.
The method will throw if the structure is not recognized. Alternatively
it is possible to use the `Type::tryFromVariable` which will instead
return `null` on unidentified type. On success both methods
return the corresponding enum `Type`.

```php
use Bakame\Http\StructuredFields\Type;

echo Type::fromVariable(42)->value;  // returns 'integer'
echo Type::fromVariable(42.0)->name; // returns 'Decimal'
echo Type::fromVariable("https://a.bébé.com"); // throws InvalidArgument
echo Type::tryFromVariable(new SplTempFileObject()); // returns null
```

To ease validation the `Type::equals`  and `Type::isOneOf` methods are added to check if
the variable is one of the expected type. It can also be used to compare types.

```php
use Bakame\Http\StructuredFields\Type;

$field = Type::fromVariable('foo');
Type::Date->equals($field);          // returns false
Type::String->equals($field);        // returns true;
Type::Boolean->equals(Type::String); // returns false
Type::fromVariable(42)->isOneOf(Type::Token, Type::Integer); //return true
```

## Custom Value Type

The RFC defines three (3) specific data types that can not be represented by
PHP default type system, for them, we have defined three classes `Token`,
`Byte` and `DisplayString` to help with their representation.

```php
use Bakame\Http\StructuredFields\Bytes;
use Bakame\Http\StructuredFields\DisplayString;
use Bakame\Http\StructuredFields\Token;

Token::fromString(string|Stringable $value): Token
Bytes::fromDecoded(string|Stringable $value): Byte;
Bytes::fromEncoded(string|Stringable $value): Byte;
DisplayString::fromDecoded(string|Stringable $value): DisplayString;
DisplayString::fromEncoded(string|Stringable $value): DisplayString;
```

All classes are final and immutable; their value can not be modified once
instantiated. To access their value, they expose the following API:

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\Bytes;
use Bakame\Http\StructuredFields\DisplayString;

$token = Token::fromString('application/text+xml');
echo $token->toString(); // returns 'application/text+xml'

$displayString = DisplayString::fromDecoded('füü');
$displayString->decoded(); // returns 'füü'
$displayString->encoded(); // returns 'f%c3%bc%c3%bc'

$byte = Bytes::fromDecoded('Hello world!');
$byte->decoded(); // returns 'Hello world!'
$byte->encoded(); // returns 'SGVsbG8gd29ybGQh'

$token->equals($byte); // will return false;
$displayString->equals($byte); // will return false;
$byte->equals(Bytes::fromEncoded('SGVsbG8gd29ybGQh')); // will return true
$displayString->equals(DisplayString::fromEncoded('f%c3%bc%c3%bc')); // will return true

$token->type();         // returns Type::Token
$byte->type();          // returns Type::Byte
$displayString->type(); // returns Type::DisplayString
```

> [!WARNING]
> The classes DO NOT expose the `Stringable` interface to help distinguish
> them from the string type or a stringable object

> [!IMPORTANT]
> Values are not directly accessible. They can only be retrieved from an Item
> Data type.

## The Item Data Type

This is the structure from which you will be able to access the actual field content.

### Item value

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
use Bakame\Http\StructuredFields\Bytes;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

Item:new(DateTimeInterface|Byte|Token|DisplayString|string|int|array|float|bool $value): self
Item:tryNew(mixed $value): ?self
Item::fromDecodedBytes(Stringable|string $value): self;
Item::fromEncodedBytes(Stringable|string $value): self;
Item::fromEncodedDisplayString(Stringable|string $value): self;
Item::fromDecodedDisplayString(Stringable|string $value): self;
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

Item::withValue(DateTimeInterface|Byte|Token|DisplayString|string|int|float|bool $value): self
```

### Item Parameters

Items can have parameters attached to them. A parameter is a **bare item**, an item which can not have parameters
attach to it, to avoid recursive behaviour. Parameters are grouped in an ordered map container called `Parameters`.
They can be accessed by their indices **but also** by their **required key** attached to them.

```php

$item = Item::fromHttpValue('application/xml;q=0.9;foobar');
$item->value()->toString(); // returns 'application/xhtml+xml'
$item->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
$item->parameterByIndex(index: 1, default: ['toto', false]); // returns ['foobar', true] because there's a parameter at index 1
$item->parameters(); // returns a Parameters instance.
```

By default, you can access the member `Item` of a parameters using the following methods:

- `Item::parameters` returns a `Parameters` instance;
- `Item::parameterByKey` returns the value of the bare item instance attached to the supplied `name`;
- `Item::parameterByIndex` returns the value of the bare item instance attached to the supplied `index`;

It is possible to alter and modify the `Parameters` attached to an `Item` but this will be explored in
the next section about the containers.

&larr; [Parsing and Serializing](parsing-serializing.md)  |  [Containers](containers.md) &rarr;
