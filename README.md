# HTTP Structured Fields for PHP

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize and build HTTP Structured Fields in PHP according to the [RFC][1].

HTTP Structured fields are intended for use for new HTTP fields that wish to use a common syntax that is
more restrictive than traditional HTTP field values or could be used to retrofit current fields value to
have them compliant with the new syntax.

```php
use Bakame\Http\StructuredFields\Item;

$field = Item::from("/terms", ['rel' => 'copyright', 'anchor' => '#foo']);
echo $field->toHttpValue();     // display "/terms";rel="copyright";anchor="#foo"
echo $field->value();           // display "/terms"
echo $field->parameter('rel');  // display "copyright"
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

or download the library and:

- use any other [PSR-4][4] compatible autoloader.
- use the bundle autoloader script as shown below:

~~~php
require 'path/to/http-structured-fields/repo/autoload.php';

use Bakame\Http\StructuredFields\OuterList;

$list = OuterList::fromHttpValue('"/member/*/author", "/member/*/comments"');
echo $list[-1]->value(); // returns '/member/*/comments'
~~~

## Documentation

### Parsing and Serializing Structured Fields

Once the library is installed parsing the header value is done via one of the package value object via its `fromHttpValue`
named constructor as shown below:

```php

declare(strict_types=1);

namespace MyApp;

require 'vendor/autoload.php';

use Bakame\Http\StructuredFields\Item;

//the HTTP request object is given by your application
// or any given framework or package.
//We use a PSR-7 Request object in this example

$headerLine = $request->getHeaderLine('foo');
$field = Item::fromHttpValue($headerLine);
$field->value();          // returns the found token value
$field->parameter('baz'); // returns the value of the parameter or null if the parameter is not defined.
```

The `fromHttpValue` method returns an instance of the `Bakame\Http\StructuredFields\StructuredField` interface.
The interface provides a way to serialize the value object into a normalized RFC compliant HTTP field
string value using the `toHttpValue` method.

To ease integration with current PHP frameworks and packages working with HTTP headers and trailers,
each value object also exposes the `Stringable` interface method `__toString` as an alias 
of the `toHttpValue` method.

````php
use Bakame\Http\StructuredFields\Item;

$bar = Item::fromToken('bar')->addParameter('baz', Item::from(42));
echo $bar->toHttpValue(); // return 'bar;baz=42'   

//the HTTP response object is given by your application
// or any given framework or package.
//We use Symfony Response object in this example
$newResponse = $response->headers->set('foo', $bar->toHttpValue());
//or
$newResponse = $response->headers->set('foo', $bar);
````

The library provides all five (5) structured defined in the RFC inside the `Bakame\Http\StructuredFields`
namespace.

- `Item`;
- `Dictionary` and `Parameters` as 2 Ordered map Containers;
- `OuterList` and `InnerList` as 2 list Containers ;

They all implement the `StructuredField` interface and expose a `fromHttpValue` named constructor:

### Building and Updating Structured Fields Values

Every value object can be used as a builder to create an HTTP field value.

The `Dictionary` and `Parameters` instances can be build with an associative iterable structure as shown below

```php
use Bakame\Http\StructuredFields\Dictionary;

$value = Dictionary::fromAssociative([
    'b' => false,
    'a' => Item::fromToken('bar')->addParameter('baz', 42),
    'c' => new DateTimeImmutable('2022-12-23 13:00:23'),
]);

echo $value->toHttpValue(); //"b=?0, a=bar;baz=42, c=@1671800423"
echo $value;                //"b=?0, a=bar;baz=42, c=@1671800423"
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

If the preference is to use the builder pattern, the same result can be achieved with the following steps:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$bar = Item::fromToken('bar')
    ->addParameter('baz', Item::from(42));
$value = Dictionary::create()
    ->add('a', $bar)
    ->prepend('b', Item::from(false))
    ->append('c', Item::from(new DateTimeImmutable('2022-12-23 13:00:23')))
;

echo $value->toHttpValue(); //"b=?0, a=bar;baz=42, c=@1671800423"
echo $value;                //"b=?0, a=bar;baz=42, c=@1671800423"
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
```

Conversely, changes can be applied to `OuterList` and `InnerList` with adapted methods around list handling:

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
$list->insert($key, ...$members): static;
$list->replace($key, $member): static;
```

Last but not least you can attach, read and update a `Parameters` instance using the
following methods on `Item` and `InnerList` instances:

```php
$field->parameter($key): mixed|null;
$field->addParameter($key, $value): static;
$field->appendParameter($key, $value): static;
$field->prependParameter($key, $value): static;
$field->withoutParameters(...$keys): static;
$field->withoutAllParameters($): static;
$field->withParameters(Parameters $parameters): static;
```

### Item and RFC Data Types

To handle an item, the package provide a specific `Item` value object with additional named constructors.
Items can have different types that are translated to PHP using:

- native type where possible
- specific classes defined in the package namespace to represent non-native type

The table below summarizes the item value type.

| HTTP DataType | Package Data Type              | Package Enum Type    |
|---------------|--------------------------------|----------------------|
| Integer       | `int`                          | `Type::Integer`      |
| Decimal       | `float`                        | `Type::Decimal`      |
| String        | `string` or `Stringable` class | `Tyoe::String`       |
| Boolean       | `bool`                         | `Type::Boolean`      |
| Token         | class `Token`                  | `Type::Token`        |
| Byte Sequence | class `ByteSequence`           | `Type::ByteSequence` |
| Date          | class `DateTimeImmutable`      | `Type::Date`         |

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;

$item = Item::fromPair([
    "hello world", [
        ["a", Item::from(ByteSequence::fromDecoded("Hello World"))],
    ]
]);
$item->value();            // returns "hello world"
$item->type();             // returns Type::String
$item->parameters("a");    // returns ByteSequence::fromDecoded('Hello World');
echo $item->toHttpValue(); // returns "hello world";a=:SGVsbG8gV29ybGQ=:
```

Once again it is possible to simplify this code using the following technique:

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;

$item = Item::from("hello world", [
    "a" => Item::fromDecodedByteSequence("Hello World")
]);
$item->value();            // returns "hello world"
$item->type();             // returns Type::String
$item->parameters("a");    // returns StructuredFields\ByteSequence::fromDecoded('Hello World');
echo $item->toHttpValue(); // returns "hello world";a=:SGVsbG8gV29ybGQ=:
```

The RFC define two (2) specific data types that can not be represented by PHP default type system, for them, we define
two classes `Token` and `ByteSequence` to help representing them in our code.

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;

Token::fromString(string|Stringable $value): self;         // from a value and an associate iterable of parameters
ByteSequence::fromDecoded(string|Stringable $value): self; // a string to convert to a Token and an associate iterable of parameters
ByteSequence::fromEncoded(string|Stringable $value): self; // a string to convert to a Byte Sequence and an associate iterable of parameters
```

**Of note: to instantiate a decimal number type a float MUST be used as the first argument of `Item::from`.**

```php
use Bakame\Http\StructuredFields\Item;

$decimal = Item::from(42.0);
$decimal->type(); //Type::Decimal

$integer = Item::fromPair([42]);
$integer->type(); //return Type::Integer
```

Here's the complete list of named constructors attached to the `Item` object. They all call the `Item::from` method, 
so they all expect an associative iterable to represents the parameters.

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Value;

//@type DataType ByteSequence|Token|DateTimeInterface|Stringable|string|int|float|bool

Item::from(DataType $value, iterable<string, Value> $associativeParameters = []): self;
Item::fromPair(array{0:DataType, 1:iterable<array{0:string, 1:DataType}>} $pair): self;
Item::fromDecodedByteSequence(string $value): self;
Item::fromEncodedByteSequence(string $value): self;
Item::fromToken(string $value): self;
Item::fromTimestamp(int $value): self;
Item::fromDateFormat(string $format, string $datetime): self;
Item::fromDateString(string $datetime, DateTimeZone|string|null $timezone): self;
```

### Accessing members of Structured Fields Containers.

`Item` are accessible using three (3) methods:

```php
use Bakame\Http\StructuredFields\Item;

$item = Item::from(CarbonImmutable::parse('today'));
$item->type();         // return Type::Date;
$item->value()         // return CarbonImmutable::parse('today') (because it extends DateTimeImmutable)
$item->parameters();   // returns a Parameters container
$item->parameter('z'); // returns the Bare Item value or null if the key does not exists
```

All containers implement PHP `IteratorAggregate`, `Countable` and `ArrayAccess` interfaces for easy usage in your codebase.
You also can access container members via the following shared methods

```php
$container->keys(): array<string>;
$container->has(string|int ...$offsets): bool;
$container->get(string|int $offset): bool;
$container->remove(string|int ...$offsets): bool;
$container->hasMembers(): bool;
$container->hasNotMembers(): bool;
```

To avoid invalid states, the modifying methods from PHP `ArrayAccess` will throw a `ForbiddenOperation` if you try to
use them:

```php
use Bakame\Http\StructuredFields\Parameters;

$value = Parameters::fromAssociative(['a' => 'foobar']);
$value->has('b');     // return false
$value['a']->value(); // return 'foobar'
$value['a'] = 23      // triggers a ForbiddenOperation exception
unset($value['a']);   // triggers a ForbiddenOperation exception
```

Apart from the PHP interfaces with the `Dictionary` and the `Parameters` classes you can access the members as pairs:

```php
$container->hasPair(int ...$offsets): bool;
$container->pair(int $offset): array{0:string, 1:StructuredField};
$container->toPairs(): iterable<array{0:string, 1:StructuredField}>;
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

[1]: https://www.rfc-editor.org/rfc/rfc8941.html
[2]: https://www.ietf.org/id/draft-ietf-httpbis-retrofit-00.html
[3]: https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
[4]: https://www.php-fig.org/psr/psr-4/
