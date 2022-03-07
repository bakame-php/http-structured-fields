Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)

The package provides pragmatic classes to manage [HTTP Structured Fields][1] in PHP.

You will be able to

- parse, serialize HTTP Structured Fields
- create, update HTTP Structured Fields from different type and sources;
- infer fields and data from HTTP Structured Fields;

```php
use Bakame\Http\StructuredField\Dictionary;

$dictionary = Dictionary::fromField("a=?0, b, c; foo=bar");
count($dictionary); // returns 3 members
$dictionary->findByKey('c')->canonical(); // returns "c; foo=bar"
$dictionary->findByIndex(2)->parameters()->findByKey('foo')->value(); // returns "bar"
$dictionary->findByIndex(0)->value(); // returns false
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

API
---

## Manipulating a Field

There are three top-level types that an HTTP field can be defined as:

- Lists,
- Dictionaries, 
- and Items.

Depending on the field to extract the package provides a specific entry point via a common named constructor `:fromField`.

```php
use Bakame\Http\StructuredField\Dictionary;
use Bakame\Http\StructuredField\Item;
use Bakame\Http\StructuredField\OrderedList;

$dictionary = Dictionary::fromField("a=?0,   b,   c; foo=bar");
$dictionary->canonical(); // "a=?0, b, c;foo=bar"
$list = OrderedList::fromField("(\"foo\"; a=1;b=2);lvl=5, (\"bar\" \"baz\");lvl=1");
$item = Item::fromField("\"foo\";a=1;b=2");
```

The `canonical()` method exposed by all the items type returns the string representation suited for HTTP headers.

### Items

Accessing `Item` properties is done via two methods:

- `Item::value()` which returns the field underlying value
- `Item::parameters()` which returns the field associated parameters as an distict `Parameters` object

```php
use Bakame\Http\StructuredField\Item;

$item = Item::fromField("\"foo\";a=1;b=2");
$item->value(); //returns "foo"
$item->parameters()->findByKey("a")->value(); //returns 1
```

As explain in the RFC, `Parameters` are containers for items attached to another datatype **BUT** the items
contained in a Parameter container can not themselves contain parameters.

The returned value of `Item::value` also depend on the item type. An Item can be:

- an Integer, 
- a Decimal, 
- a String, 
- a Token, 
- a Byte Sequence, 
- or a Boolean.

| HTTP DataType definition | Package Type representation | Named constructor        |
|--------------------------|-----------------------------|--------------------------|
| Integer                  | int                         | `Item::fromInteger`      |
| Decimal                  | float                       | `Item::fromDecimal`      |
| String                   | string                      | `Item::fromString`       |
 | Boolean                  | bool                        | `Item::fromBoolean`      |
| Token                    | Bakame\Http\StructuredField\Token            | `Item::fromToken`        |
| Byte Sequence            | Bakame\Http\StructuredField\ByteSequence     | `Item::fromByteSequence` |

As you can see, two classes `Token` and `ByteSequence` are used to represent non native types.

## Items Containers

The other types defined by the RFC are essentially different items containers with different requirements. As
such `Parameters`, `Dictionary`, `InnerList` and `OrderedList` expose the same public API.

```php
namespace Bakame\Http\StructuredField;

interface ItemTypeContainer extends \Countable, \IteratorAggregate, StructuredField
{
    public function isEmpty(): bool;
    public function findByIndex(string $index): Item|InnerList|null;
    public function findByKey(int $key): Item|InnerList|null;
}
```

This means that at any given time it is possible to know:

- If the container is empty via the `isEmpty` method;
- The number of elements contained in the container via the `count` method;
- fetch any element by its string `index` or by its position integer `key` via `findByIndex` and `findByKey` methods;
- and to iterate over each contained members via the `IteratorAggregate` interface;

```php
use Bakame\Http\StructuredField\Item;

$item = Item::fromField("\"foo\";a=1;b=2");
$item->value(); //returns "foo"
$parameters = $item->parameters(); // a Parameters object is a ItemTypeContainer
count($parameters); // return 2
$parameters->findByKey('b'); // return 2
$parameters->findByIndex(1);     // return 2
$parameters->findByIndex(42);    // return null because the key does not exist.
$parameters->canonical();      // return ";a=1;b=2"
```

The `Parameters` and the `Dictionary` classes allow associating index to members as such they expose

- the `keyExists` method, to verify the existence of a specific index;
- the `set` method expect a key and an structured field type;
- the `unset` method expect a list of keys to remove it and its associated field type;

```php
use Bakame\Http\StructuredField\Dictionary;
use Bakame\Http\StructuredField\Item;

$dictionary = Dictionary::fromField("a=?0, b, c; foo=bar");
$dictionary->keyExists('a'); //return true
$dictionary->keyExists('foo'); //return false
$dictionary->set('z', Item::fromDecimal(42));
$dictionary->unset('b', 'c');
echo $dictionary->canonical(); //returns "a=?0, z=42.0"
```

The `OrderedList` and the `InnerList` classes are list of members as such they expose

- the `indexExists` method, to verify the existence of a specific index;
- the `push` method expect a list of data type to append to it;
- the `unshift` method expect a list of data type to prepend to it;
- the `insert` method expect a list of data type to prepend to insert at a specific index;
- the `replace` method expect a data type to replace the current one at a specific index;
- the `remove` method expect a list key to remove them and their respective associated data type;

**EVERY CHANGE IN THE LIST WILL RE-INDEX THE LIST AS TO NOT EXPOSE MISSING KEYS**

```php
use Bakame\Http\StructuredField\OrderedList;

$list = OrderedList::fromField("(\"foo\" \"bar\"), (\"baz\"), (\"bat\" \"one\"), ()");
$list->indexExists(2); //return true
$list->indexExists(42); //return false
$list->push(Item::fromDecimal(42));
$list->remove(0, 2);
echo $list->canonical(); //returns "("baz"), (), 42.0"
```

For more on the restriction applied to all the containers please refer to the RFC.

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
- [All Contributors](https://github.com/thephpleague/uri/contributors)

Attribution
-------

This package is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[1]: https://www.rfc-editor.org/rfc/rfc8941.html
