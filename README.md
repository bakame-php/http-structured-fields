Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

The package uses value objects to parse, serialize and build [HTTP Structured Fields][1] in PHP.

HTTP Structured fields are intended for use by specifications of new HTTP fields that wish to 
use a common syntax that is more restrictive than traditional HTTP field values or could
be used to [retrofit current headers][2] to have them compliant with the new syntax.

The package can be used to:

- parse and serialize HTTP Structured Fields
- build or update HTTP Structured Fields in a predicable way;

```php
use Bakame\Http\StructuredFields;

$field = StructuredFields\Item::from("/terms", ['rel' => 'copyright', 'anchor' => '#foo']);
echo $field->toHttpValue();            //display "/terms";rel="copyright";anchor="#foo"
echo $field->value;                    //display "/terms"
echo $field->parameters->value('rel'); //display "copyright"
```

System Requirements
-------

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

Installation
------------

Use composer:

```
composer require bakame/http-structured-fields
```

or download the library and:

- use any other [PSR-4][4] compatible autoloader.
- use the bundle autoloader script as shown below:

~~~php
require 'path/to/http-structured-fields/repo/autoload.php';

use Bakame\Http\StructuredFields;

$list = StructuredFields\OrderedList::fromHttpValue('"/member/*/author", "/member/*/comments"');
echo $list->get(-1)->value; //returns '/member/*/comments'
~~~

Parsing and Serializing Structured Fields
------------

```php
use Bakame\Http\StructuredFields;

$dictionary = StructuredFields\Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
echo $dictionary->toHttpValue(); // 'a=?0, b, c;foo=bar'

$list = StructuredFields\OrderedList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
echo $list->toHttpValue(); // '("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1'

$item = StructuredFields\Item::fromHttpValue('"foo";a=1;b=2');
echo $item->toHttpValue(); // "foo";a=1;b=2
```

an HTTP field value can be:

- a Dictionary,
- a List,
- an Item.

For each of these top-level types, the package provides a dedicated value object to parse the textual 
representation of the field and to serialize the value object back to the textual representation. 

- Parsing is done via a common named constructor `fromHttpValue` which expects the Header or Trailer string value.
- Serializing is done via a common `toHttpValue` public method. The method returns the normalized string representation suited for HTTP textual representation.

Building Structured Fields
------------

### Items

#### Definitions

Items can have different types [defined in the RFC][3]. 

They are translated to PHP using:

- native type where possible 
- specific classes defined in the package namespace to represent non-native type

The table below summarize the item value type.

| HTTP DataType | Package Data Type    | validation method      |
|---------------|----------------------|------------------------|
| Integer       | `int`                | `Item::isInteger`      |
| Decimal       | `float`              | `Item::isDecimal`      |
| String        | `string`             | `Item::isString`       |
| Boolean       | `bool`               | `Item::isBoolean`      |
| Token         | class `Token`        | `Item::isToken`        |
| Byte Sequence | class `ByteSequence` | `Item::isByteSequence` |

Items can be associated with an ordered maps of key-value pairs also known as parameters, where the 
keys are strings and the value are bare items. Their public API is covered in subsequent paragraphs.

**An item without any parameter associated to it is said to be a bare item.**

#### Token type

```php
use Bakame\Http\StructuredFields;

$token = StructuredFields\Token::fromString('bar')]));

echo $token->toString();         //displays 'bar'
echo $dictionary->toHttpValue(); //displays 'bar'
```

The Token data type is a special string as defined in the RFC. To distinguish it from a normal string, the `Bakame\Http\StructuredFields\Token` class is used.

To instantiate the class you are required to use the `Token::fromString` named constructor.
The class also exposes the complementary public methods `Token::toString` as well as the `Token::toHttpValue` to enable its textual representation.

#### Byte Sequence type

```php
use Bakame\Http\StructuredFields;

$sequenceFromDecoded = StructuredFields\ByteSequence::fromDecoded("Hello World");
$sequenceFromEncoded = StructuredFields\ByteSequence::fromEncoded("SGVsbG8gV29ybGQ=");

echo $sequenceFromEncoded->decoded();     //displays 'Hello World'
echo $sequenceFromDecoded->encoded();     //displays 'SGVsbG8gV29ybGQ='
echo $sequenceFromDecoded->toHttpValue(); //displays ':SGVsbG8gV29ybGQ=:'
echo $sequenceFromEncoded->toHttpValue(); //displays ':SGVsbG8gV29ybGQ=:'
```

The Byte Sequence data type is a special string as defined in the RFC to represent base64 encoded data. To distinguish it from a normal string, 
the `Bakame\Http\StructuredFields\ByteSequence` class is used.

To instantiate the class you are required to use the `ByteSequence::fromDecoded` or `ByteSequence::fromEncoded` named constructors.
The class also exposes the complementary public methods `ByteSequence::decoded`, `ByteSequence::encoded` as well as 
the `ByteSequence::toHttpValue` to enable its textual representation.

#### Usages

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::from("hello world", ["a" => true]);
$item->value;      //returns "hello world"
$item->isString(); //returns true
$item->isToken();  //returns false
$item->parameters->value("a"); //returns true
```

Instantiation via type recognition is done using the `Item::from` named constructor.

- The first argument represents one of the six (6) item type value;
- The second argument, which is optional, MUST be an iterable construct where its index represents the parameter key and its value an item or a item type value;

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::fromPair([
    "hello world", 
    [
        ["a", StructuredFields\ByteSequence::fromDecoded("Hello World")],
    ]
]);
$item->value; //returns "hello world"
$item->isString(); //return true
$item->parameters->get("a")->isByteSequence(); //returns true
$item->parameters->value("a")->decoded(); //returns 'Hello World'
echo $item->toHttpValue(); //returns "hello world";a=:SGVsbG8gV29ybGQ=:
```

Conversely, the `Item::fromPair` is an alternative to the `Item::from`
which expects a tuple composed by an array as a list where:

- The first member on index `0` represents one of the six (6) item type value;
- The second optional member, on index `1`, MUST be an iterable construct containing tuples of key-value pairs;

Once instantiated, accessing `Item` properties is done via two (2) readonly properties:

- `Item::value` which returns the instance underlying value
- `Item::parameters` which returns the parameters associated to the `Item` as a distinct `Parameters` object

**Of note: to instantiate a decimal number type a float MUST be used as the first argument of `Item::from`.**

```php
use Bakame\Http\StructuredFields;

$decimal = StructuredFields\Item::from(42.0);
$decimal->isDecimal(); //return true
$decimal->isInteger(); //return false

$item = StructuredFields\Item::fromPair([42]);
$item->isDecimal(); //return false
$item->isInteger(); //return true
```

### Containers

```php
use Bakame\Http\StructuredFields;

$parameters = StructuredFields\Parameters::fromAssociative(['a' => 1, 'b' => 2, 'c' => "hello world"]);
count($parameters); // returns 3
$parameters->isEmpty(); // returns false
$parameters->toHttpValue(); // returns ';a=1;b=2;c="hello world"'
$parameters->clear()->isEmpty(); // returns true
```

The package exposes ordered maps and lists with different requirements via the following value objects:

- `Dictionary`,
- `Parameters`,
- `OrderedList`,
- and `InnerList`

At any given time it is possible with each of these objects to:

- iterate over its members using the `IteratorAggregate` interface;
- know the number of members it contains via the `Countable` interface;
- tell whether the container is empty via an `isEmpty` method;
- clear its content using the `clear` method;

**Of note:** 

- All setter methods are chainable 
- For setter methods, Item types are inferred using `Item::from` if a `Item` object is not submitted.
- Because all containers can be access by their indexes, some changes may re-index them as to not expose missing indexes.

#### Ordered Maps

```php
use Bakame\Http\StructuredFields;

$dictionary = StructuredFields\Dictionary::fromPairs([
    ['b', true],
]);
$dictionary
    ->append('c', StructuredFields\Item::from(true, ['foo' => StructuredFields\Token::fromString('bar')]))
    ->prepend('a', false)
    ->toHttpValue(); //returns "a=?0, b, c;foo=bar"

$dictionary->has('a');    //return true
$dictionary->has('foo');  //return false
$dictionary->pair(1);     //return ['b', Item::fromBoolean(true)]
$dictionary->hasPair(-1); //return true

echo $dictionary
    ->append('z', 42.0)
    ->delete('b', 'c')
    ->toHttpValue(); //returns "a=?0, z=42.0"
```

The `Parameters` and the `Dictionary` classes allow associating a string 
key to its members 

- `Parameters` can only contain `Item` instances 
- `Dictionary` instance can contain `Item` and/or `InnerList` instances.

Both classes exposes the following:

named constructors:

- `fromAssociative` instantiates the container with an iterable construct of associative value;
- `fromPairs` instantiates the container with a list of key-value pairs;

getter methods:

- `toPairs` returns an iterator to iterate over the container pairs;
- `keys` to list all existing keys of the ordered maps as an array list;
- `has` tell whether a specific element is associated to a given `key`;
- `hasPair` tell whether a `key-value` association exists at a given `index` (negative indexes are supported);
- `get` returns the element associated to a specific `key`;
- `pair` returns the key-pair association present at a specific `index` (negative indexes are supported);

setter methods:

- `set` add an element at the end of the container if the key is new otherwise only the value is updated;
- `append` always add an element at the end of the container, if already present the previous value is removed;
- `prepend` always add an element at the beginning of the container, if already present the previous value is removed;
- `delete` to remove elements based on their associated keys;
- `mergeAssociative` merge multiple instances of iterable structure as associative constructs;
- `mergePairs` merge multiple instances of iterable structure as pairs constructs;

The `Parameters` instance exposes the following additional methods:

- `Parameters::values()` to list all existing Bare Items value as an array list;
- `Parameters::value(string $key)` to return the value of the Bare Item associated to the `$key` or `null` if the key is unknown or invalid;
- `Parameters::sanitize()` to return an instance where all Items present in the container are Bare Items. Any non Bared Item instance will see its parameters getting clear up.

```php
use Bakame\Http\StructuredFields;

$parameters = StructuredFields\Parameters::fromAssociative(['b' => true, 'foo' => 'bar']);
$parameters->keys(); // returns ['b', 'foo']
$parameters->values(); // returns [true, 'bar']
$parameters->value('b'); // returns true
$parameters->get('b'); // returns Item::from(true)
iterator_to_array($parameters->toPairs(), true); // returns [['b', Item::from(true)], ['foo', Item::from('bar')]]
iterator_to_array($parameters, true); // returns ['b' => Item::from(true), 'foo' => Item::from('bar')]
$parameters->mergeAssociative(
    StructuredFields\Parameters::fromPairs([['b', true], ['foo', 'foo']]),
    ['b' => 'false']
);
$parameters->toHttpValue(); // returns ;b="false";foo="foo"
$parameters->value('unknown'); // returns null
```

#### Lists

```php
use Bakame\Http\StructuredFields;

$innerList = StructuredFields\InnerList::fromList([42, 42.0, "42"], ["a" => true]);
$innerList->has(2); //return true
$innerList->has(42); //return false
$innerList->push(StructuredFields\Token::fromString('forty-two'));
$innerList->remove(0, 2);
echo $innerList->toHttpValue(); //returns '(42.0 forty-two);a'

$orderedList = StructuredFields\OrderedList::from(
    StructuredFields\Item::from("42", ["foo" => "bar"]), 
    $innerList
);
echo $orderedList->toHttpValue(); //returns '"42";foo="bar", (42.0 forty-two);a'
```

The `OrderedList` and the `InnerList` classes are list of members that act as containers 

The main distinction between `OrderedList` and `InnerList` are:

- `OrderedList` members must be `InnerList` or `Items`;
- `InnerList` members must be `Items`;
- `InnerList` can have a `Parameters` instance attached to it;

Both classes exposes the following:

named constructors:

- `fromList` instantiates the container with a list of members in an iterable construct;
- `from` instantiates the container with a list of members as variadic;

getter methods:

- `get` to access an element at a given index (negative indexes are supported)
- `has` tell whether an element is attached to the container using its `index`;

setter methods

- `push` to add elements at the end of the list;
- `unshift` to add elements at the beginning of the list;
- `insert` to add elements at a given position in the list; 
- `replace` to replace an element at a given position in the list;
- `remove` to remove elements based on their position;

A `Parameters` instance can be associated to an `InnerList` using the same API as for the `Item` value object.

```php
use Bakame\Http\StructuredFields;

$innerList = StructuredFields\InnerList::fromList([42, 42.0, "42"], ["a" => true]);
$innerList->parameters; //returns a StructuredFields\Parameters object
$innerList->parameters->value('a'); // returns true
```

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

Testing
-------

The library:

- has a [PHPUnit](https://phpunit.de) test suite
- has a coding style compliance test suite using [PHP CS Fixer](https://cs.sensiolabs.org/).
- has a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).
- is compliant with [the language agnostic HTTP Structured Fields Test suite](https://github.com/httpwg/structured-field-tests).

To run the tests, run the following command from the project folder.

``` bash
$ composer test
```

Security
-------

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/http-structured-fields/contributors)

Attribution
-------

The package internal parser is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[1]: https://www.rfc-editor.org/rfc/rfc8941.html
[2]: https://www.ietf.org/id/draft-ietf-httpbis-retrofit-00.html
[3]: https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
[4]: https://www.php-fig.org/psr/psr-4/
