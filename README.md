# HTTP Structured Fields for PHP

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize 
build and update HTTP Structured Fields in PHP according to the [RFC8941](https://www.rfc-editor.org/rfc/rfc8941.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OuterList;
use Bakame\Http\StructuredFields\Token;

//1 - parsing an Accept Header
$headerValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$field = OuterList::fromHttpValue($headerValue);
$field[2]->value()->toString(); // returns 'application/xml'
$field[2]->parameter('q');      // returns (float) 0.9
$field[0]->value()->toString(); // returns 'text/html'
$field[0]->parameter('q');      // returns null

//2 - building a Retrofit Cookie Header
echo OuterList::new(
        InnerList::fromAssociative(['foo', 'bar'], [
            'expire' => $expire,
            'path' => '/',
            'max-age' => 2500,
            'secure' => true,
            'httponly' => true,
            'samesite' => Token::fromString('lax'),
        ])
    )
    ->toHttpValue();
// returns ("foo" "bar");expire=@1681504328;path="/";max-age=2500;secure;httponly=?0;samesite=lax
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

## Documentation

### Foreword

**⚠️WARNING: While this package parses and serializes the header value, it does not validate its content.
It is still required to validate the parsed data against the constraints of the corresponding
header. Content validation is out of scope for this library.**

### Parsing and Serializing Structured Fields

Parsing the header value is done via the `fromHttpValue` named constructor.
The method is attached to each library's structured fields representation
as shown below:

```php
declare(strict_types=1);

require 'vendor/autoload.php';

use Bakame\Http\StructuredFields\Item;

// the raw HTTP field value is given by your application
// via any given framework, package or super global.

$headerLine = 'bar;baz=42'; //the raw header line is a structured field item
$field = Item::fromHttpValue($headerLine);
$field->value();          // returns Token::fromString('bar); the found token value 
$field->parameter('baz'); // returns 42; the value of the parameter or null if the parameter is not defined.
```

The `fromHttpValue` method returns an instance which implements the `StructuredField` interface.
The interface provides the `toHttpValue` method that serializes it into a normalized RFC
compliant HTTP field string value. To ease integration, the `__toString` method is
implemented as an alias to the `toHttpValue` method.

````php
use Bakame\Http\StructuredFields\Item;

$field = Item::fromHttpValue('bar;    baz=42;     secure=?1');
echo $field->toHttpValue(); // return 'bar;baz=42;secure'
// on serialization the field has been normalized

// the HTTP response is build by your application
// via any given framework, package or PHP native function.

header('foo: '. $field->toHttpValue());
//or
header('foo: '. $field);
````

All five (5) structured data type as defined in the RFC are provided inside the
`Bakame\Http\StructuredFields` namespace. They all implement the
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

| RFC Type      | PHP Type                  | Package Enum Type    |
|---------------|---------------------------|----------------------|
| Integer       | `int`                     | `Type::Integer`      |
| Decimal       | `float`                   | `Type::Decimal`      |
| String        | `string`                  | `Type::String`       |
| Boolean       | `bool`                    | `Type::Boolean`      |
| Token         | class `Token`             | `Type::Token`        |
| Byte Sequence | class `ByteSequence`      | `Type::ByteSequence` |
| Date          | class `DateTimeImmutable` | `Type::Date`         |

The Enum `Type` which list all available types can be use to determine the RFC type
corresponding to a PHP structure using the `Type::fromValue` static method.
The method will throw if the structure is not recognized Alternatively it is possible
to use the `Type::tryFromValue` which will instead return `null` on unindentified type.
On success both methods returns the corresponding enum `Type`.

```php
use Bakame\Http\StructuredFields\Type;

echo Type::fromValue(42);         // returns Type::Integer
echo Type::fromValue(42.0)->name; // returns 'Decimal'
echo Type::fromValue(new SplTempFileObject());    // throws InvalidArgument
echo Type::tryFromValue(new SplTempFileObject()); // returns null
```

To ease validation a `Type::equals` method is exposed to check if the `Item` has
the expected type. It can also be used to compare types.

```php
use Bakame\Http\StructuredFields\Type;

$field = Item::fromHttpValue('"foo"');
Type::Date->equals($field);          // returns false
Type::String->equals($field);        // returns true;
Type::Boolean->equals(Type::String); // returns false
```

The RFC defines two (2) specific data types that can not be represented by
PHP default type system, for them, we have defined two classes `Token` 
and `ByteSequence` to help with their representation.

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;

Token::fromString(string|Stringable $value): Token
ByteSequence::fromDecoded(string|Stringable $value): ByteSequence;
ByteSequence::fromEncoded(string|Stringable $value): ByteSequence;
```

Both classes are final and immutable; their value can not be modified once
instantiated. To access their value, they expose the following API:

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

**⚠️WARNING: Both classes DO NOT expose the `Stringable` interface to distinguish them
from a string or a string like object**

#### Item

The defined types are all attached to an `Item` object where their value and
type are accessible using the following methods:

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Type;

$item = Item::fromHttpValue('@1234567890');
$item->type();  // return Type::Date;
$item->value()  // return the equivalent to DateTimeImmutable('@1234567890');
```

#### Containers

All containers objects implement PHP `IteratorAggregate`, `Countable` and `ArrayAccess`
interfaces. Their members can be accessed using the following shared methods

```php
$container->keys(): array<string|int>;
$container->has(string|int ...$offsets): bool;
$container->get(string|int $offset): StrucuredField;
$container->hasMembers(): bool;
$container->hasNoMembers(): bool;
```

**The `get` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.**

To avoid invalid states, `ArrayAccess` modifying methods throw a `ForbiddenOperation`
if you try to use them on any container object:

```php
use Bakame\Http\StructuredFields\Parameters;

$value = Parameters::fromHttpValue(';a=foobar']);
$value->has('b');     // return false
$value['a']->value(); // return 'foobar'
$value['b'];          // triggers a InvalidOffset exception, the index does not exist
$value['a'] = 23      // triggers a ForbiddenOperation exception
unset($value['a']);   // triggers a ForbiddenOperation exception
```

The `Dictionary` and `Parameters` classes also allow accessing its members as pairs:

```php
$container->hasPair(int ...$offsets): bool;
$container->pair(int $offset): array{0:string, 1:StructuredField};
$container->toPairs(): iterable<array{0:string, 1:StructuredField}>;
```

**The `pair` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.**

#### Accessing the parameters values

Accessing the associated `Parameters` instance attached to an `InnerList` or a `Item` instances
is done using the following methods:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Parameters;

$field->parameter(string $key): ByteSequence|Token|DateTimeImmutable|Stringable|string|int|float|bool|null;
$field->parameters(): Parameters;
$field->parameterByIndex(int $index): array{0:string, 1:ByteSequence|Token|DateTimeImmutable|Stringable|string|int|float|boo}|array{}
InnerList::toPair(): array{0:list<Item>, 1:Parameters}>};
Item::toPair(): array{0:ByteSequence|Token|DateTimeImmutable|Stringable|string|int|float|bool, 1:Parameters}>};
```

- **The `parameter` method will return `null` if no value is found for the given key.**
- **The `parameterByIndex` method is added in `version 1.1.0` and returns an empty array if no parameter is found for the given index.**

### Building and Updating Structured Fields Values

Every value object can be used as a builder to create an HTTP field value. Because we are
using immutable value objects any change to the value object will return a new instance
with the changes applied and leave the original instance unchanged.

#### Items value

The `Item` value object exposes the following named constructors to instantiate
bare items (ie: item without parameters attached to them).

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

Item:new(DateTimeInterface|ByteSequence|Token|string|int|float|bool $value): self
Item::fromDecodedByteSequence(Stringable|string $value): self;
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

Item::withValue(DateTimeInterface|ByteSequence|Token|string|int|float|bool $value): static
```

#### Ordered Maps

The `Dictionary` and `Parameters` are ordered map instances. They can be built using their keys with an associative iterable structure as shown below

```php
use Bakame\Http\StructuredFields\Dictionary;

$value = Dictionary::fromAssociative([
    'b' => Item::false(),
    'a' => Item::fromToken('bar'),
    'c' => new DateTimeImmutable('2022-12-23 13:00:23'),
]);

echo $value->toHttpValue(); //"b=?0, a=bar, c=@1671800423"
echo $value;                //"b=?0, a=bar, c=@1671800423"
```

or using their indexes with an iterable structure of pairs (tuple) as defined in the RFC:

```php
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\Item;

$value = Parameters::fromPairs(new ArrayIterator([
    ['b', Item::false()],
    ['a', Item::fromToken('bar')],
    ['c', new DateTime('2022-12-23 13:00:23')]
]));

echo $value->toHttpValue(); //;b=?0;a=bar;c=@1671800423
echo $value;                //;b=?0;a=bar;c=@1671800423
```

If the preference is to use the builder pattern, the same result can be achieved with the 
following steps:

- First create a `Parameters` or a `Dictionary` instance using the `new` named constructor which
returns a new instance with no members.
- And then, use any of the following modifying methods to populate it.

```php
$map->add(string $key, $value): static;
$map->append(string $key, $value): static;
$map->prepend(string $key, $value): static;
$map->mergeAssociative(...$others): static;
$map->mergePairs(...$others): static;
$map->remove(string ...$key): static;
```

As shown below:
`
```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$value = Dictionary::new()
    ->add('a', InnerList::new(
        Item::fromToken('bar'),
        Item::fromString('42'),
        Item::fromInteger(42),
        Item::fromDecimal(42)
     ))
    ->prepend('b', Item::false())
    ->append('c', Item::fromDateString('2022-12-23 13:00:23'))
;

echo $value->toHttpValue(); //b=?0, a=(bar "42" 42 42.0), c=@1671800423
echo $value;                //b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

Since version `1.1.0` it is possible to also build `Dictionary` and `Parameters` instances
using indexes and pair as per described in the RFC.

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

```php
// since version 1.1
$map->unshift(array ...$pairs): static;
$map->push(array ...$pairs): static;
$map->insert(int $key, array ...$pairs): static;
$map->replace(int $key, array $pair): static;
```

We can rewrite the previous example

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$value = Dictionary::new()
    ->push(
        ['a', InnerList::new(
            Item::fromToken('bar'),
            Item::fromString('42'),
            Item::fromInteger(42),
            Item::fromDecimal(42)
         )],
         ['c', Item::true()]
     )
    ->unshift(['b', Item::false()])
    ->replace(2, ['c', Item::fromDateString('2022-12-23 13:00:23')])
;

echo $value->toHttpValue(); //b=?0, a=(bar "42" 42 42.0), c=@1671800423
echo $value;                //b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

**⚠️WARNING: on duplicate `keys` pair values are merged as per RFC logic.**

#### Automatic conversion

For all containers, to ease instantiation the following automatic conversion are applied on
the member argument of each modifying methods.

If the submitted type is:

-  a `StructuredField` implementing object, it will be passed as is
-  an iterable structure, it will be converted to an `InnerList` instance using `InnerList::new`
-  otherwise, it is converted into an `Item` using the `Item::new` named constructor.

If no conversion is possible an `InvalidArgument` exception will be thrown.

This means that both constructs below built equal objects

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

echo Dictionary::new()
    ->add('a', InnerList::new(
        Item::fromToken('bar'),
        Item::fromString('42'),
        Item::fromInteger(42),
        Item::fromDecimal(42)
     ))
    ->prepend('b', Item::false())
    ->append('c', Item::fromDateString('2022-12-23 13:00:23'))
    ->toHttpValue()
;

echo Dictionary::new()
    ->add('a', [Token::fromString('bar'), '42', 42, 42.0])
    ->prepend('b', false)
    ->append('c', new DateTimeImmutable('2022-12-23 13:00:23'))
    ->toHttpValue()
;

 // both will return 'b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

Of course, it is possible to mix both notations.

#### Lists

To create `OuterList` and `InnerList` instances you can use the `new` named constructor
which takes a single variadic parameter `$members`:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\ByteSequence;

$list = InnerList::new(
    ByteSequence::fromDecoded('Hello World'),
    42.0,
    42
);

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

Once again, the builder pattern can be used via a combination of the `new`
named constructor and the use any of the following modifying methods.

```php
$list->unshift(...$members): static;
$list->push(...$members): static;
$list->insert(int $key, ...$members): static;
$list->replace(int $key, $member): static;
$list->remove(int ...$key): static;
```

as shown below

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\InnerList;

$list = InnerList::new()
    ->unshift('42')
    ->push(42)
    ->insert(1, 42.0)
    ->replace(0, ByteSequence::fromDecoded('Hello World'));

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

#### Adding and updating parameters

To ease working with instances that have a `Parameters` object attached to, the following
methods are added:

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

//@type SfItemInput ByteSequence|Token|DateTimeInterface|string|int|float|bool

Item::fromAssociative(SfItemInput $value, Parameters|iterable<string, SfItemInput> $parameters): self;
Item::fromPair(array{0:SfItemInput, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>} $pair): self;

InnerList::fromAssociative(iterable<SfItemInput> $value, Parameters|iterable<string, SfItemInput> $parameters): self;
InnerList::fromPair(array{0:iterable<SfItemInput>, Parameters|iterable<array{0:string, 1:SfItemInput}>} $pair): self;
```

The following example illustrate how to use those methods:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;

echo Item::fromAssociative(
        Token::fromString('bar'),
        ['baz' => 42]
    )->toHttpValue(), PHP_EOL;

echo Item::fromPair([
        Token::fromString('bar'),
        [['baz', 42]],
    ])->toHttpValue(), PHP_EOL;

//both methods return `bar;baz=42`
```

Both objects provide additional modifying methods to help deal with parameters.
You can attach and update the associated `Parameters` instance using the following methods.

```php
$field->addParameter(string $key, mixed $value): static;
$field->appendParameter(string $key, mixed $value): static;
$field->prependParameter(string $key, mixed $value): static;
$field->withoutParameters(string ...$keys): static;
$field->withoutAnyParameter(): static;
$field->withParameters(Parameters $parameters): static;
```
Since version `1.1` it is also possible to use the index of each member to perform additional
modifications.

```php
$field->pushParameters(array ...$pairs): static
$field->unshiftParameters(array ...$pairs): static
$field->insertParameters(int $index, array ...$pairs): static
$field->replaceParameter(int $index, array $pair): static
```

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

**⚠️WARNING: The return value will be the parent class an NOT a `Parameters` instance**

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;

echo InnerList::new('foo', 'bar')
    ->addParameter('expire', Item::fromDateString('+30 minutes'))
    ->addParameter('path', '/')
    ->addParameter('max-age', 2500)
    ->toHttpValue();

echo InnerList::new('foo', 'bar')
    ->pushParameter(
        ['expire', Item::fromDateString('+30 minutes')],
        ['path', '/'],
        ['max-age', 2500],
    )
    ->toHttpValue();

// both flow return the InnerList HTTP value 
// ("foo" "bar");expire=@1681538756;path="/";max-age=2500
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
