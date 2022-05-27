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

getter methods:

- `toPairs` returns an iterator to iterate over the container pairs;
- `keys` to list all existing keys of the ordered maps as an array list;
- `has` tell whether a specific element is associated to a given `key`;
- `hasPair` tell whether a `key-value` association exists at a given `index`;
- `get` returns the element associated to a specific `key`;
- `pair` returns the key-pair association present at a specific `index`;

setter methods:

- `set` add an element at the end of the container if the key is new otherwise only the value is updated;
- `append` always add an element at the end of the container, if already present the previous value is removed;
- `prepend` always add an element at the beginning of the container, if already present the previous value is removed;
- `delete` to remove elements based on their associated keys;
- `mergeAssociative` merge multiple instances of iterable structure as associative constructs;
- `mergePairs` merge multiple instances of iterable structure as pairs constructs;
- `sanitize` normalize the container.

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

## Dictionary specific methods

- `Dictionary::sanitize` returns an instance where all included items or containers are sanitized.

## Parameters specific methods

The `Parameters` instance exposes the following additional methods:

- `Parameters::values` to list all existing Bare Items value as an array list;
- `Parameters::value` to return the value of the Bare Item associated to the `$key` or `null` if the key is unknown or invalid;
- `Parameters::sanitize` returns an instance where all Items present in the container are Bare Items.
  Any non Bared Item instance will see its parameters getting clear up.

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

**Both classes support negative indexes.**
