# Lists

## Definitions

The `OrderedList` and the `InnerList` classes are list of members that act as containers

The main distinction between `OrderedList` and `InnerList` are:

- `OrderedList` members must be `InnerList` or `Items`;
- `InnerList` members must be `Items`;
- `InnerList` can have a `Parameters` instance attached to it;

## Common methods

Both classes expose the following:

named constructors:

- `fromList` instantiates the container with a list of members in an iterable construct;
- `new` instantiates the container with a list of members as variadic;

setter methods

- `push` to add elements at the end of the list;
- `unshift` to add elements at the beginning of the list;
- `insert` to add elements at a given position in the list;
- `replace` to replace an element at a given position in the list;
- `remove` to remove elements based on their position;

In addition to `StructuredField` specific interfaces, both classes implements:

- PHP `Countable` interface.
- PHP `IteratorAggregate` interface.
- PHP `ArrayAccess` interface.

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OrderedList;
use Bakame\Http\StructuredFields\Token;

$innerList = InnerList::fromList([42, 42.0, "42"], ["a" => true]);
$innerList->has(2); //return true
$innerList->has(42); //return false
$innerList->push(Token::fromString('forty-two'));
$innerList->remove(0, 2);
echo $innerList->toHttpValue(); //returns '(42.0 forty-two);a'

$orderedList = OrderedList::from(
    Item::from("42", ["foo" => "bar"]), 
    $innerList
);
echo $orderedList->toHttpValue(); //returns '"42";foo="bar", (42.0 forty-two);a'
```

Same example using the `ArrayAccess` interface.

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Token;

$innerList = InnerList::fromList([42, 42.0, "42"], ["a" => true]);
isset($innerList[2]); //return true
isset($innerList[42]); //return false
$innerList[] = Token::fromString('forty-two');
unset($innerList[0], $innerList[2]);
echo $innerList->toHttpValue(); //returns '(42.0 forty-two);a'
```

**if you try to set a key which does not exist an exception will be
thrown as both classes must remain valid lists with no empty
keys. Be aware that re-indexation behaviour may affect
your logic**

```php
use Bakame\Http\StructuredFields\OrderedList;
use Bakame\Http\StructuredFields\Token;

$innerList = OrderedList::fromList([42, 42.0]);
$innerList[2] = Token::fromString('forty-two'); // will throw
echo $innerList->toHttpValue(), PHP_EOL;
```
