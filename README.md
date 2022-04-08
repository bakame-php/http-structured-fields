Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

The package uses value objects to parse, serialize and buikd [HTTP Structured Fields][1] in PHP.

HTTP Structured fields are intended for use by specifications of new HTTP fields that wish to 
use a common syntax that is more restrictive than traditional HTTP field values or could
be used to [retrofit current headers][2] to have them compliant with the new syntax.

The package can be used to:

- parse and serialize HTTP Structured Fields
- create and update HTTP Structured Fields in a predicable way;
- infer fields and data types from HTTP Structured Fields;

```php
use Bakame\Http\StructuredFields;

$field = StructuredFields\Item::from("/terms", ['rel' => "copyright", 'anchor' => '#foo']);
echo $field->toHttpValue();           //display "/terms";rel="copyright";anchor="#foo"
echo $field->value;                   //display "/terms"
echo $field->parameters->value('rel'); //display "copyright"
```

System Requirements
-------

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

Installation
------------

Using composer:

```
composer require bakame/http-structured-fields
```

Documentation
---

## Parsing and Serializing Structured Fields

There are three top-level types that an HTTP field can be defined as:

- Dictionaries,
- Lists,
- and Items.

For each of those top-level types, the package provide a dedicated value object to parse the textual 
representation of the field and to serialize the value object back to the textual representation. 

- Parsing is done via a common named constructor `fromHttpValue` which expects the Header or Trailer string value.
- Serializing is done via a common `toHttpValue` public method. The method returns the normalized string representation suited for HTTP textual representation.

```php
use Bakame\Http\StructuredFields;

$dictionary = StructuredFields\Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
echo $dictionary->toHttpValue(); // "a=?0, b, c;foo=bar"

$list = StructuredFields\OrderedList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
echo $list->toHttpValue(); // "("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1"

$item = StructuredFields\Item::fromHttpValue('"foo";a=1;b=2"');
echo $item->toHttpValue(); // "foo";a=1;b=2
```

## Structured Fields Data Types

The RFC defines different data types to handle structured fields values.

### Items

Items are the minimal building block for structured fields the following explains how to build 
and interact with them.

#### Bare Items

Items can have different types [defined in the RFC][3]. They are translated to PHP native type 
when possible. Two additional classes

- `Bakame\Http\StructuredFields\Token` and
- `Bakame\Http\StructuredFields\ByteSequence`

are used to represent non-native types as shown in the table below: 

| HTTP DataType | Returned value       | validation method      |
|---------------|----------------------|------------------------|
| Integer       | `int`                | `Item::isInteger`      |
| Decimal       | `float`              | `Item::isDecimal`      |
| String        | `string`             | `Item::isString`       |
| Boolean       | `bool`               | `Item::isBoolean`      |
| Token         | class `Token`        | `Item::isToken`        |
| Byte Sequence | class `ByteSequence` | `Item::isByteSequence` |

#### Extended Items

Item can be associated with `Parameters` that are ordered maps of key-value pairs, where the 
keys is a string and the value are bare items. Their public API will be cover in subsequent paragraphs.

#### Usage

Instantiation via type recognition is done using the `Item::from` named constructor.

- The first argument represents one of the six (6) item type value;
- The second argument, which is optional, MUST be an iterable construct where its index represents the parameter key and its value an item or a item type value;

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::from("hello world", ["a" => 1]);
$item->value; //returns "hello world"
$item->isString(); //return true
$item->isToken();  //return false
$item->parameters->value("a"); //returns 1
```

Conversely, the `Item::fromPair` is an alternative to the `Item::from`
which expects a tuple composed by an array as w list where:

- The first member on index `0` represents one of the six (6) item type value;
- The second optional member, on index `1`, MUST be an iterable construct where its index represents the parameter key and its value an item or a item type value;

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::fromPair(["hello world", ["a" => 1]]);
$item->value; //returns "hello world"
$item->isString(); //return true
$item->isToken();  //return false
$item->parameters->value("a"); //returns 1
```

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

Apart from the `Item`, the RFC defines different items containers with different requirements. The
package exposes those containers via the following value objects:

- `Dictionary`,
- `Parameters`,
- `OrderedList`,
- and `InnerList`

At any given time it is possible with each of these objects to:

- iterate over its members using the `IteratorAggregate` interface;
- tell whether the container is empty via an `isEmpty` method;
- know the number of members contained in the container via the `Countable` interface;
- clear the container using the `clear` method;

```php
use Bakame\Http\StructuredFields;

$parameters = StructuredFields\Parameters::fromAssociative(['a' => 1, 'b' => 2, 'c' => "hello world"]);
count($parameters);          // return 2
$parameters->isEmpty();      // returns false
$parameters->toHttpValue();  // return ";a=1;b=2"
```

#### Ordered Maps

The `Parameters` and the `Dictionary` classes allow associating a string 
key to its members as such they expose 

the following getter methods:

- `fromAssociative` a named constructor to instantiate the container with an associative array;
- `fromPairs` a named constructor to instantiate the container with a list of key-value pairs;
- `toPairs` returns an iterator to iterate over the container pairs;
- `keys` to list all existing keys of the ordered maps as an array list;
- `has` tell whether a specific element is associated to a given `key`;
- `hasPair` tell whether a `key-value` association exists at a given `index` (negative indexes are supported);
- `get` returns the element associated to a specific `key`;
- `pair` returns the key-pair association present at a specific `index` (negative indexes are supported);

the following setter methods:

- `set` add an element at the end of the container if the key is new otherwise only the value is updated;
- `append` always add an element at the end of the container, if already present the previous value is removed;
- `prepend` always add an element at the beginning of the container, if already present the previous value is removed;
- `delete` to remove elements based on their associated keys;
- `mergeAssociative` merge multiple instances of iterable structure as associative constructs;
- `mergePairs` merge multiple instances of iterable structure as pairs constructs;

**All setter methods are chainable.**

```php
use Bakame\Http\StructuredFields;

$dictionary = StructuredFields\Dictionary::fromPairs([['b', true]]);
$dictionary
    ->append('c', StructuredFields\Item::from(true, ['foo' => StructuredFields\Token::fromString('bar')]))
    ->prepend('a', false)
    ->toHttpValue(); //returns "a=?0, b, c;foo=bar"

$dictionary->has('a');   //return true
$dictionary->has('foo'); //return false
$dictionary->pair(1); //return ['b', Item::fromBoolean(true)]
$dictionary->hasPair(-1);  //return true

echo $dictionary
    ->append('z', 42.0)
    ->delete('b', 'c')
    ->toHttpValue(); //returns "a=?0, z=42.0"
```

**Item types are inferred using `Item::from` if a `Item` object is not submitted.**

- `Parameters` can only contains `Item` instances 
- `Dictionary` instance can contain `Item` and `InnerList` instances.

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
    StructuredFields\Parameters::fromAssociative(['b' => true, 'foo' => 'foo']),
    ['b' => 'false']
);
$parameters->toHttpValue(); // returns ;b="false";foo="foo"
$parameters->value('unknown'); // returns null
```

#### Lists

The `OrderedList` and the `InnerList` classes are list of members 
that act as containers and also expose the following 

getter methods:

- `fromList` a named constructor to instantiate the container with a list of members in an iterable construct;
- `from` a named constructor to instantiate the container with a list of members as variadic;
- `get` to access an element at a given index (negative indexes are supported)
- `has` tell whether an element is attached to the container using its `index`;

setter methods (**All setter methods are chainable.**)

- `push` to add elements at the end of the list;
- `unshift` to add elements at the beginning of the list;
- `insert` to add elements at a given position in the list; 
- `replace` to replace an element at a given position in the list;
- `remove` to remove elements based on their position;

to enable manipulation their content.

**Item types are inferred using `Item::from` if a `Item` object is not submitted.**

**EVERY CHANGE IN THE LIST WILL RE-INDEX THE LIST AS TO NOT EXPOSE MISSING INDEXES**

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

The distinction between `InnerList` and `OrderedList` is well explained in the 
RFC but the main ones are:

- `InnerList` members must be `Items`;
- `OrderedList` members must be `InnerList` or `Items`;
- `InnerList` has a `Parameters` instance attached to it that you can access via its readonly property `parameters`, not `OrderedList`;

```php
use Bakame\Http\StructuredFields;

$innerList = StructuredFields\InnerList::fromList([42, 42.0, "42"], ["a" => true]);
$innerList->parameters;             //returns a StructuredFields\Parameters object
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

This package is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[1]: https://www.rfc-editor.org/rfc/rfc8941.html
[2]: https://www.ietf.org/id/draft-ietf-httpbis-retrofit-00.html
[3]: https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
