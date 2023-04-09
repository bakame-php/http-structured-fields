# HTTP Structured Fields for PHP

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize 
and build HTTP Structured Fields in PHP according to the [RFC8941](https://www.rfc-editor.org/rfc/rfc8941.html).

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

## Documentation

### Parsing and Serializing Structured Fields

Once the library is installed parsing the header value is done via the normalized `fromHttpValue` named 
constructor attached to library's structured fields representation as shown below:

```php

declare(strict_types=1);

require 'vendor/autoload.php';

use Bakame\Http\StructuredFields\Item;

// the raw HTTP field value is given by your application
// via any given framework or package or super global.
// We are using a PSR-7 Request object in this example

$headerLine = $request->getHeaderLine('foo'); // 'foo: bar;baz=42' the raw header line is a structured field item
$field = Item::fromHttpValue($headerLine);
$field->value();          // returns Token::fromString('bar); the found token value 
$field->parameter('baz'); // returns 42; the value of the parameter or null if the parameter is not defined.
```

The `fromHttpValue` method returns an instance which implements the`StructuredField` interface.
The interface provides a way to serialize the object into a normalized RFC compliant HTTP 
field string value using the `StructuredField::toHttpValue` method.

To ease integration with current PHP frameworks and packages working with HTTP headers and trailers,
each value object also exposes the `Stringable` interface method `__toString` as an alias to 
the `toHttpValue` method.

````php
use Bakame\Http\StructuredFields\Item;

$bar = Item::fromToken('bar')->addParameter('baz', 42);
echo $bar->toHttpValue(); // return 'bar;baz=42'   

// the HTTP response object is build by your application
// via your framework, a package or a native PHP function.
// We are using Symfony Response object in this example

$newResponse = $response->headers->set('foo', $bar->toHttpValue());
//or
$newResponse = $response->headers->set('foo', $bar);
````

The library provides all five (5) structured data type as defined in the RFC inside the
`Bakame\Http\StructuredFields` namespace. As mentioned, they all implement the
`StructuredField` interface and expose a `fromHttpValue` named constructor:

- `Item`
- `Parameters`
- `Dictionary`
- `OuterList` (named `List` in the RFC but renamed in the package because `list` is a reserved word in PHP.)
- `InnerList`

### Accessing Structured Fields Values

#### RFC Value type

Per the RFC, items can have different types that are translated to PHP using:

- native type where possible
- specific classes defined in the package namespace to represent non-native type

The table below summarizes the item value type.

| RFC Type      | PHP Type                       | Package Enum Type    |
|---------------|--------------------------------|----------------------|
| Integer       | `int`                          | `Type::Integer`      |
| Decimal       | `float`                        | `Type::Decimal`      |
| String        | `string` or `Stringable` class | `Tyoe::String`       |
| Boolean       | `bool`                         | `Type::Boolean`      |
| Token         | class `Token`                  | `Type::Token`        |
| Byte Sequence | class `ByteSequence`           | `Type::ByteSequence` |
| Date          | class `DateTimeImmutable`      | `Type::Date`         |

As shown in the table, the RFC define two (2) specific data types that can not be represented by
PHP default type system, for them, we have defined two classes `Token` and `ByteSequence` to help
with represention.

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;

Token::fromString(string|Stringable $value): Token
ByteSequence::fromDecoded(string|Stringable $value): ByteSequence;
ByteSequence::fromEncoded(string|Stringable $value): ByteSequence;
```

Both classes are final and immutable; their value can not be modified once instantiated.
To access their value, they expose the following API:

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;

$token = Token::fromString('application/text+xml');
echo $token->toString(); // returns 'application/text+xml'

$byte = ByteSequence::fromDecoded('Hello world!');
$byte->decoded(); // returns 'Hello world!'
$byte->encoded(); // returns 'SGVsbG8gd29ybGQh'

$token->equals($byte); // will return false;
$byte->equals(ByteSequence::fromEncoded('SGVsbG8gd29ybGQh')); // will return true

$token->type(); // returns Type::Token enum
$byte->type();  // returns Type::ByteSequence
```

**Both classes DO NOT expose the `Stringable` interface to distinguish them
from a string or a string like object**

#### Item

The defined types are all attached to the `Item` object where there values and types
are accessible using the following methods:

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Type;

//@type SfItemInput ByteSequence|Token|DateTimeImmutable|Stringable|string|int|float|bool
// the Item::value() can return one of those type
$item = Item::fromDate(CarbonImmutable::parse('today'));
$item->type();  // return Type::Date;
$item->value()  // return a CarbonImmutable instance because it extends DateTimeImmutable
// you can also do 
Type::Date->equals($item); // returns true
```

#### Containers

All containers objects implement PHP `IteratorAggregate`, `Countable` and `ArrayAccess` interfaces for
easy usage in your codebase. You also can access container members via the following shared methods

```php
$container->keys(): array<string>;
$container->has(string|int ...$offsets): bool;
$container->get(string|int $offset): StrucuredField;
$container->hasMembers(): bool;
$container->hasNoMembers(): bool;
```

To avoid invalid states, the modifying methods from PHP `ArrayAccess` will throw a `ForbiddenOperation`
if you try to use them on any container object:

```php
use Bakame\Http\StructuredFields\Parameters;

$value = Parameters::fromAssociative(['a' => 'foobar']);
$value->has('b');     // return false
$value['a']->value(); // return 'foobar'
$value['b'];          // triggers a SyntaxError exception, the index does not exist
$value['a'] = 23      // triggers a ForbiddenOperation exception
unset($value['a']);   // triggers a ForbiddenOperation exception
```

Apart from the PHP interfaces, the `Dictionary` and `Parameters` classes allow accessing its members
as pairs:

```php
$container->hasPair(int ...$offsets): bool;
$container->pair(int $offset): array{0:string, 1:StructuredField};
$container->toPairs(): iterable<array{0:string, 1:StructuredField}>;
```

#### Accessing the parameters values

You can also read the associated `Parameters` instance attached to an `InnerList` or a `Item` instances
using the following methods:

```php
use Bakame\Http\StructuredFields\Parameters;

$field->parameter(string $key): ByteSequence|Token|DateTimeImmutable|Stringable|string|int|float|bool|null;
$field->parameters(): Parameters;
```

**Of note: the `parameter` method will return `null` if no value is found for the given index.**

### Building and Updating Structured Fields Values

Every value object can be used as a builder to create an HTTP field value.

#### Items value

The `Item` value object exposes lots of named constructors to construct
bare items (ie: item without parameters attached to them).

```php
use Bakame\Http\StructuredFields\Item;

Item::fromDecodedByteSequence(Stringable|string $value): self;
Item::fromEncodedByteSequence(Stringable|string $value): self;
Item::fromToken(Stringable|string $value): self;
Item::fromString(Stringable|string $value): self;
Item::fromTimestamp(int $value): self;
Item::fromDateFormat(string $format, string $datetime): self;
Item::fromDateString(string $datetime, DateTimeZone|string|null $timezone = null): self;
Item::fromDate(DateTimeInterface $datetime): self;
Item::fromDecimal(int|float $value): self;
Item::fromInteger(int|float $value): self;
Item::true(): self;
Item::false(): self;
```

To update an `Item` object value, the `Item::withValue` method should be use:

```php
use Bakame\Http\StructuredFields\Item;

Item::withValue(SfItemInput $value): static
```

#### Dictionaries

The `Dictionary` and `Parameters` instances can be build with an associative iterable structure as shown below

```php
use Bakame\Http\StructuredFields\Dictionary;

$value = Dictionary::fromAssociative([
    'b' => false,
    'a' => Item::fromToken('bar'),
    'c' => new DateTimeImmutable('2022-12-23 13:00:23'),
]);

echo $value->toHttpValue(); //"b=?0, a=bar, c=@1671800423"
echo $value;                //"b=?0, a=bar, c=@1671800423"
```

Or with an iterable structure of pairs as per defined in the RFC:

```php
use Bakame\Http\StructuredFields\Parameters;

$value = Parameters::fromPairs([
    ['b', false],
    ['a', Item::fromPair([Token::fromString('bar')])],
    ['c', new DateTime('2022-12-23 13:00:23')]
]);

echo $value->toHttpValue(); //;b=?0;a=bar;c=@1671800423
echo $value;                //;b=?0;a=bar;c=@1671800423
```

If the preference is to use the builder pattern, the same result can be achieved with the following steps.
We start building a `Parameters` or a `Dictionary` instance using the `create` named constructor which
returns a new instance with no members.

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$value = Dictionary::create()
    ->add('a', Item::fromToken('bar'))
    ->prepend('b', Item::false())
    ->append('c', Item::fromDateString('2022-12-23 13:00:23'))
;

echo $value->toHttpValue(); //"b=?0, a=bar, c=@1671800423"
echo $value;                //"b=?0, a=bar, c=@1671800423"
```

Because we are using immutable value objects any change to the value object will return a new instance with
the changes applied and leave the original instance unchanged.

`Dictionary` and `Parameters` exhibit the following modifying methods:

```php
$map->add($key, $value): static;
$map->append($key, $value): static;
$map->prepend($key, $value): static;
$map->mergeAssociative(...$others): static;
$map->mergePairs(...$others): static;
$map->remove(...$key): static;
```

#### Lists

To Create `OuterList` and `InnerList` instances you can use the `from` named constructor:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;

$list = InnerList::from(
    Item::fromDecodedByteSequence('Hello World'),
    42.0,
    42
);

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

Once again, builder methods exist on both classes to ease container construction.

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;

$list = InnerList::from()
    ->unshift('42')
    ->push(42)
    ->insert(1, 42.0)
    ->replace(0, Item::fromDecodedByteSequence('Hello World'));

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

`OuterList` and `InnerList` exhibit the following modifying methods:

```php
$list->unshift(...$members): static;
$list->push(...$members): static;
$list->insert(int $key, ...$members): static;
$list->replace(int $key, $member): static;
$list->remove(int ...$key): static;
```

#### Adding and updating parameters

To ease working with instance that have a `Parameters` object attached to the following
public API is added. It is also possible to instantiate an `InnerList` or an `Item`
instance with included parameters using one of these named constructors:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;

//@type SfItemInput ByteSequence|Token|DateTimeInterface|Stringable|string|int|float|bool

Item::fromAssociative(SfItemInput $value, iterable<string, SfItemInput>|Parameters $parameters = []): self;
Item::fromPair(array{0:SfItemInput, 1:iterable<array{0:string, 1:SfItemInput}>|Parameters} $pair): self;

InnerList::fromAssociative(iterable<SfItemInput> $members, iterable<string, SfItemInput>|Parameters $parameters): self;
InnerList::fromPair(array{0:iterable<SfItemInput>, iterable<array{0:string, 1:SfItemInput}>|Parameters} $pair): self;
```

Both classes allow return their respective pair representation via the `toPair` method.

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Parameters;

InnerList::toPair(): array{0:list<Item>, 1:Parameters}>};
Item::toPair(): array{0:mixed, 1:Parameters}>};
```

Both objects provide additional modifying methods to help deal with parameters.
You can attach and update the associated `Parameters` instance using the following methods:

```php
use Bakame\Http\StructuredFields\Parameters;

$field->addParameter(string $key, mixed $value): static;
$field->appendParameter(string $key, mixed $value): static;
$field->prependParameter(string $key, mixed $value): static;
$field->withoutParameters(string ...$keys): static;
$field->withoutAnyParameter(): static;
$field->withParameters(Parameters $parameters): static;
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Testing

The library:

- has a [PHPUnit](https://phpunit.de) test suite
- has a coding style compliance test suite using [PHP CS Fixer](https://cs.sensiolabs.org/).
- has a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).
- is compliant with [the language agnostic HTTP Structured Fields Test suite](https://github.com/httpwg/structured-field-tests).

To run the tests, run the following command from the project folder.

``` bash
composer test
```

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/http-structured-fields/contributors)

## Attribution

The package internal parser is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
