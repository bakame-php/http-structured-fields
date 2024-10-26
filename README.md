# HTTP Structured Fields for PHP

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize 
create, update and validate HTTP Structured Fields in PHP according to the [RFC9651](https://www.rfc-editor.org/rfc/rfc9651.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\OuterList;

//1 - parsing an Accept Header
$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$field = OuterList::fromRfc9651($fieldValue);
$field[1]->value()->toString(); // returns 'application/xhtml+xml'
$field[1]->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

## Documentation

> [!WARNING]
> The documentation for v2 is still not fully finished please refers to [version 1.x](https://github.com/bakame-php/http-structured-fields/tree/1.x)
> for the most recent and stable documentation.

The package is compliant against [RFC9651](https://www.rfc-editor.org/rfc/rfc9651.html) as such
it exposes all the data type and all the methods expected to comply with the RFC requirements

### Basic Usage

#### Parsing the Field

The first way to use the package is to enable header or trailer parsing. We will refers to them as field
for the rest of the documentation as it is how they are called hence the name of the RFC HTTP structured fields.

Let's say we want to parse the [Permissions-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy#syntax) header as it is defined. 
Because the header is defined as a Dictionary field we can easily parse it using the package as follows:

```php
$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; //the raw header line is a structured field item
$permissions = Dictionary::fromHttpValue($headerLine); // parse the field
```

You will now be able to access each persimission individually as follows:

```php
$permissions['picture-in-picture']->hasNoMembers(); //returns true because the list is empty
$permissions['geolocation'][1]->value(); //returns 'https://example.com/'
count($permissions['geolocation']); // returns 2
$permissions['camera']->value(); //returns '*'
```

Apart from following the specification some syntactic sugar methods have been added to allow for easy access
of the values.

> [!WARNING]
> If parsing or serializing is not possible, a `SyntaxError` exception is thrown with the information about why
the conversion could not be achieved.

#### Building the Field

Conversely, if you need to update the permission header, the package allows for a intuitive way to do so:

```php
$newPermissions = $permissions
    ->remove('camera')
    ->add('gyroscope',  [
        Token::fromString('self'), 
        "https://a.example.com", 
        "https://b.example.com"
    ]);
echo $newPermissions; 
//returns picture-in-picture=(), geolocation=(self "https://example.com/"), gyroscope=(self "https://a.example.com" "https://b.example.com")
```

Again if the value given is not supported by the structured field `Dictionary` specification an exception will be
raised. All RFC related classes in the package are immutable which allow for easier manipulation. For a more in depth
presentation of each structure and their method please refer to the 

### Structured Fields Values

The package provides methods to access the field values and convert them to PHP type whenever possible.The table below summarizes the value type.

| RFC Type      | PHP Type                  | Package Enum Name     | Package Enum Value | RFC min. version |
|---------------|---------------------------|-----------------------|--------------------|------------------|
| Integer       | `int`                     | `Type::Integer`       | `ìnteger`          | RFC8941          |
| Decimal       | `float`                   | `Type::Decimal`       | `decimal`          | RFC8941          |
| String        | `string`                  | `Type::String`        | `string`           | RFC8941          |
| Boolean       | `bool`                    | `Type::Boolean`       | `boolean`          | RFC8941          |
| Token         | class `Token`             | `Type::Token`         | `token`            | RFC8941          |
| Byte Sequence | class `ByteSequence`      | `Type::ByteSequence`  | `binary`           | RFC8941          |
| Date          | class `DateTimeImmutable` | `Type::Date`          | `date`             | RFC9651          |
| DisplayString | class `DisplayString`     | `Type::DisplayString` | `displaystring`    | RFC9651          |

> [!WARNING]
> The translation to PHP native type does not mean that all PHP values are usable. For instance, the integer
> or the date range supported by the RFC is smaller that the range allowed by PHP.

```php
$headerLine = 'bar;baz=42'; //the raw header line is a structured field item
$field = Item::fromRFC8941($headerLine); // parses the field
$field->value(); // returns Token::fromString('bar');
$field->value()->toString(); //return the 'bar'
$field->parameterByKey('baz'); // returns (int) 42
$field->parameterByIdex(0);    //returns ['baz' => 42];
```

To comply with the RFC the package allows selecting parameters by key or by index.

### Building and Updating Structured Fields Values

#### Items value

The `Item` value object exposes the following named constructors to instantiate
bare items (ie: item without parameters attached to them).

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

Item:new(DateTimeInterface|ByteSequence|Token|DisplayString|string|int|array|float|bool $value): self
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
$map->remove(string|int ...$key): static;
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

It is possible to also build `Dictionary` and `Parameters` instances
using indexes and pair as per described in the RFC.

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

```php
$map->unshift(array ...$pairs): static;
$map->push(array ...$pairs): static;
$map->insert(int $key, array ...$pairs): static;
$map->replace(int $key, array $pair): static;
$map->removeByKeys(string ...$keys): static;
$map->removeByIndices(int ...$indices): static;
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

> [!CAUTION]
> on duplicate `keys` pair values are merged as per RFC logic.

The `remove` always accepted string or integer as input.

```php
$field = Dictionary::fromHttpValue('b=?0, a=(bar "42" 42 42.0), c=@1671800423');
echo $field->remove('b', 2)->toHttpValue(); // returns a=(bar "42" 42 42.0)
```

If a stricter approach is needed, use the following new methods `removeByIndices` and/or `removeByKeys`:

```php
use Bakame\Http\StructuredFields\Parameters;

$field = Parameters::fromHttpValue(';expire=@1681504328;path="/";max-age=2500;secure;httponly=?0;samesite=lax');
echo $field->removeByIndices(4, 2, 0)->toHttpValue();                      // returns ;path="/";secure;samesite=lax
echo $field->removeByKeys('expire', 'httponly', 'max-age')->toHttpValue(); // returns ;path="/";secure;samesite=lax
```

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

It is also possible to create an `OuterList` based on an iterable structure
of pairs.

```php
use Bakame\Http\StructuredFields\OuterList;

$list = OuterList::fromPairs([
    [
        ['foo', 'bar'],
        [
            ['expire', new DateTime('2024-01-01 12:33:45')],
            ['path', '/'],
            [ 'max-age', 2500],
            ['secure', true],
            ['httponly', true],
            ['samesite', Token::fromString('lax')],
        ]
    ],
    [
        'coucoulesamis', 
        [['a', false]],
    ]
]);
```

The pairs definitions are the same as for creating either a `InnerList` or an `Item` using
their respective `fromPair` method.

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
$field->withoutParameters(string ...$keys): static; // this method is deprecated as of version 1.1 use withoutParametersByKeys instead
$field->withoutAnyParameter(): static;
$field->withParameters(Parameters $parameters): static;
```

It is also possible to use the index of each member to perform additional modifications.

```php
$field->pushParameters(array ...$pairs): static
$field->unshiftParameters(array ...$pairs): static
$field->insertParameters(int $index, array ...$pairs): static
$field->replaceParameter(int $index, array $pair): static
$field->withoutParametersByKeys(string ...$keys): static
$field->withoutParametersByIndices(int ...$indices): static
```

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

> [!WARNING]
> The return value will be the parent class an NOT a `Parameters` instance

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

### Validation

The package also can help with validating your field. If we go back to our example about the permission policy.
We assumed that we indeed parsed a valid field but nothing can prevent us from parsing a completely unrelated
field also defined as a dictionary field and pretend it to be a permission policy field.

A way to prevent that is to add simple validation rules on the field value or structure.

#### Validating a Bare Item.

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

#### Validating a single Parameter.

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

#### Validating the Parameters container.

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
