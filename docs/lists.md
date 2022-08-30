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
- `from` instantiates the container with a list of members as variadic;

setter methods

- `push` to add elements at the end of the list;
- `unshift` to add elements at the beginning of the list;
- `insert` to add elements at a given position in the list;
- `replace` to replace an element at a given position in the list;
- `remove` to remove elements based on their position;

Additionally, both classes implements PHP `ArrayAccess` interface as syntactic sugar methods
around the `get`, `has`, `push`, `remove` and `replace` methods.

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

Same example using the `ArrayAccess` interface.

```php
use Bakame\Http\StructuredFields;

$innerList = StructuredFields\InnerList::fromList([42, 42.0, "42"], ["a" => true]);
isset($innerList[2]); //return true
isset($innerList[42]); //return false
$innerList[] = StructuredFields\Token::fromString('forty-two');
unset($innerList[0], $innerList[2]);
echo $innerList->toHttpValue(); //returns '(42.0 forty-two);a'
```

**if you try to set a key which does not exist an exception will be
thrown as both classes must remain valid lists with no empty
keys. Be aware that re-indexation behaviour may affect
your logic**

```php
use Bakame\Http\StructuredFields;

$innerList = StructuredFields\OrderedList::fromList([42, 42.0, "42"], ["a" => true]);
$innerList[2] = StructuredFields\Token::fromString('forty-two'); // will throw
```
