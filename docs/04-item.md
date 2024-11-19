---
title: The Structured Field Item Data Type
order: 5
---

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
$item->parameterByName(name: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
$item->parameterByIndex(index: 1, default: ['toto', false]); // returns ['foobar', true] because there's a parameter at index 1
$item->parameters(); // returns a Parameters instance.
```

By default, you can access the member `Item` of a parameters using the following methods:

- `Item::parameters` returns a `Parameters` instance;
- `Item::parameterByName` returns the value of the bare item instance attached to the supplied `name`;
- `Item::parameterByIndex` returns the value of the bare item instance attached to the supplied `index`;

It is possible to alter and modify the `Parameters` attached to an `Item` but this section
will be explored in the next section about the containers.

&larr; [Value Types](03-value-types.md)  |  [Containers](05-containers.md) &rarr;
