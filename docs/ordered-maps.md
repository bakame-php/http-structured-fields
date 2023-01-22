# Ordered Maps

## Definitions

The `Dictionary` and the `Parameters` classes allow associating a string key to its members

- `Dictionary` instance can contain `Item` and/or `InnerList` instances.
- `Parameters` can only contain `Item` instances

## Common Methods

Both classes expose the following:

named constructors:

- `fromAssociative` instantiates the container with an iterable construct of associative value;
- `fromPairs` instantiates the container with a list of key-value pairs;
- `create` instantiates the container with no members;

getter methods:

- `toPairs` returns an iterator to iterate over the container pairs;
- `keys` to list all existing keys of the ordered maps as an array list;
- `hasPair` tell whether a `key-value` association exists at a given `index`;
- `pair` returns the key-pair association present at a specific `index`;

setter methods:

- `set` add an element at the end of the container if the key is new otherwise only the value is updated;
- `append` always add an element at the end of the container, if already present the previous value is removed;
- `prepend` always add an element at the beginning of the container, if already present the previous value is removed;
- `delete` to remove elements based on their associated keys;
- `mergeAssociative` merge multiple instances of iterable structure as associative constructs;
- `mergePairs` merge multiple instances of iterable structure as pairs constructs;

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$dictionary = Dictionary::fromPairs([
    ['b', true],
]);
$dictionary
    ->append('c', Item::from(true, ['foo' => Token::fromString('bar')]))
    ->prepend('a', false)
    ->toHttpValue(); //returns "a=?0, b, c;foo=bar"

$dictionary->has('a');    //return true
$dictionary->has('foo');  //return false
$dictionary->pair(1);     //return ['b', Item::fromBoolean(true)]
$dictionary->hasPair(-1); //return true

echo $dictionary
    ->append('z', 42.0)
    ->delete('b', 'c')
    ->toHttpValue(); // returns "a=?0, z=42.0"
```

In addition to `StructuredField` specific interfaces, both classes implements:

- PHP `Countable` interface.
- PHP `IteratorAggregate` interface.
- PHP `ArrayAccess` interface.

```php
use Bakame\Http\StructuredFields\Parameters;

$parameters = Parameters::fromAssociative(['b' => true, 'foo' => 'bar']);
$parameters->keys();       // returns ['b', 'foo']
$parameters->get('b');     // returns Item::from(true)
$parameters['b'];          // returns Item::from(true)
$parameters['b']->value(); // returns true
iterator_to_array($parameters->toPairs(), true); // returns [['b', Item::from(true)], ['foo', Item::from('bar')]]
iterator_to_array($parameters, true); // returns ['b' => Item::from(true), 'foo' => Item::from('bar')]
$parameters->mergeAssociative(
    Parameters::fromPairs([['b', true], ['foo', 'foo']]),
    ['b' => 'false']
);
$parameters->toHttpValue(); // returns ;b="false";foo="foo"
```

**Both classes support negative indexes.**
