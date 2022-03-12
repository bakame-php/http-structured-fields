Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)

The package uses pragmatic value objects to parse and serialize [HTTP Structured Fields][1] in PHP.

You will be able to:

- parse and serialize HTTP Structured Fields
- create and update HTTP Structured Fields in a predicable way;
- infer fields and data types from HTTP Structured Fields;

```php
use Bakame\Http\StructuredFields\Item;

$fields = Item::from("/terms", ['rel' => "copyright", 'anchor' => '#foo']));
echo $fields->toHttpValue(); //display "/terms";rel="copyright";anchor="#foo"
```

System Requirements
-------

- You require **PHP >= 8.1** but the latest stable version of PHP is recommended

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

- Lists,
- Dictionaries, 
- and Items.

For each of those top-level types, the package provide a dedicated value object to parse the textual representation of the field
and to serialize the value object back to the textual representation. 

- Parsing is done via a common named constructor `fromHttpValue` which expects the Header or Trailer string value.
- Serializing is done via a common `toHttpValue` public method. The method returns the normalized string representation suited for HTTP textual representation

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OrderedList;

$dictionary = Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
echo $dictionary->toHttpValue(); // "a=?0, b, c;foo=bar"

$list = OrderedList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
echo $list->toHttpValue(); // "("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1"

$item = Item::fromHttpValue('"foo";a=1;b=2"');
echo $item->toHttpValue(); // "foo";a=1;b=2
```

## Structured Data Types

### Items

#### Types

Bare types defined in the RFC are translated to PHP 
native type when possible. Two additional classes:

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

#### Parameters

As explain in the RFC, `Parameters` are containers of `Item` instances. They can be associated
to an `Item` instance or other container types  **BUT** the items it contains can not 
themselves contain `Parameters` instance. More on parameters public API 
will be cover in subsequent paragraphs.

#### Examples

Instantiation via type recognition is done using the `Item::from` named constructor.

```php
use Bakame\Http\StructuredFields\Item;

$item = Item::from("hello world", ["a" => 1]);
$item->value(); //returns "hello world"
$item->isString(); //return true
$item->isToken();  //return false
$item->parameters()->getByKey("a")->value(); //returns 1
```

Once instantiated, accessing `Item` properties is done via two methods:

- `Item::value()` which returns the instance underlying value
- `Item::parameters()` which returns the item associated parameters as a distinct `Parameters` object

**To instantiate a decimal number a float MUST be used as the first argument input.**

```php
use Bakame\Http\StructuredFields\Item;

$decimal = Item::from(42.0);
$decimal->isDecimal(); //return true
$decimal->isInteger(); //return false

$item = Item::from(42);
$item->isDecimal(); //return false
$item->isInteger(); //return true
```

### Containers

Apart from the `Item`, the RFC defines different containers with different requirements. The
package exposes those containers via the following methods `Parameters`, `Dictionary`, 
`InnerList` and `OrderedList` with the same basic public API. At any given time it 
is possible to:

- tell whether the container is empty via an `isEmpty` method;
- know the number of elements contained in the container via the `Countable` interface;
- iterate over each contained elements and its optional associated key via the `IteratorAggregate` interface;
- tell whether an element is attached to the container using its `index` or  `key` via `hasIndex` and `hasKey` methods;
- get any element by its string `key` or by its integer `index` via `getByKey` and `getByIndex` methods when applicable;
- merge multiple instance of the same container using the `merge` method;

```php
use Bakame\Http\StructuredFields\Parameters;

$parameters = new Parameters(['a' => 1, 'b' => 2, 'c' => Item::from("hello world")]);
count($parameters);          // return 2
$parameters->getByKey('b');  // return Item::from(2);
$parameters->getByIndex(-1); // return Item::from("hello world");
$parameters->hasKey(42);     // return false because the key does not exist.
$parameters->toHttpValue();  // return ";a=1;b=2"
$parameters->keys();         // return ["a", "b", "c"]
```
- *`getByIndex` supports negative index*
- *Item types are inferred using `Item::from` if a `Item` object is not submitted.* 

#### Ordered Maps

The `Parameters` and the `Dictionary` classes allow associating a string 
key to its members as such they expose the following methods:

- `set` add an element at the end of the container if the key is new otherwise only the value is updated;
- `append` always add an element at the end of the container, if already present the previous value is removed;
- `prepend` always add an element at the beginning of the container, if already present the previous value is removed;
- `delete` to remove elements based on their associated keys;

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;

$dictionary = new Dictionary();
$dictionary->set('b', true);
$dictionary->append('c', Item::from(true, ['foo' => new Token('bar')]));
$dictionary->prepend('a', false);
$dictionary->toHttpValue(); //returns "a=?0, b, c;foo=bar"
$dictionary->hasKey('a');   //return true
$dictionary->hasKey('foo'); //return false
$dictionary->getByIndex(1); //return Item::fromBoolean(true)
$dictionary->append('z', 42.0);
$dictionary->delete('b', 'c');
echo $dictionary->toHttpValue(); //returns "a=?0, z=42.0"
```

- `Parameters` can only contains `Item` instances 
- `Dictionary` instance can contain `Item` and `InnerList` instances.

#### Lists

The `OrderedList` and the `InnerList` classes are list of members 
that act as containers and also expose the following methods

- `push` to add elements at the end of the list;
- `unshift` to add elements at the beginning of the list;
- `insert` to add elements at a given position in the list; 
- `replace` to replace an element at a given position in the list;
- `remove` to remove elements based on their position;

to enable manipulation their content.

**EVERY CHANGE IN THE LIST WILL RE-INDEX THE LIST AS TO NOT EXPOSE MISSING INDEXES**

```php
use Bakame\Http\StructuredFields\OrderedList;

$list = OrderedList::fromHttpValue("(\"foo\" \"bar\"), (\"baz\"), (\"bat\" \"one\"), ()");
$list->hasIndex(2); //return true
$list->hasIndex(42); //return false
$list->push(42);
$list->remove(0, 2);
echo $list->toHttpValue(); //returns "("baz"), (), 42.0"
```

The distinction between `InnerList` and `OrderedList` is well explained in the 
RFC but the main ones are:

- `InnerList` members can be `Items` or `null`;
- `OrderedList` members can be `InnerList`, `Items`;
- `InnerList` can have a `Parameters` instance attached to it, not `OrderedList`;

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

Testing
-------

The library has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](https://cs.sensiolabs.org/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

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
