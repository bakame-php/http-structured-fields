# Containers

```php
use Bakame\Http\StructuredFields\Parameters;

$parameters = Parameters::fromAssociative(['a' => 1, 'b' => 2, 'c' => "hello world"]);
count($parameters); // returns 3
$parameters->hasMembers(); // returns true
$parameters->toHttpValue(); // returns ';a=1;b=2;c="hello world"'
$parameters->clear()->hasMembers(); // returns false
```

The package exposes [ordered maps](ordered-maps.md)

- Dictionary,
- and Parameters,

and [lists](lists.md) 

- OrderedList,
- and InnerList

with different requirements.

At any given time it is possible with each of these objects to:

- iterate over its members using the `IteratorAggregate` interface;
- know the number of members it contains via the `Countable` interface;
- tell whether the container contains or not members with the `hasMembers` methods from the `Container` interface;
- to access the members using the methods exposed by the `ArrayAccess`. **WARNING: Updating using `ArrayAccess` method is forbidden and will result in a `LogicException` being emitted.**;

getter methods:

- `get` to access an element at a given index (negative indexes are supported)
- `has` tell whether an element is attached to the container using its `index`;

**Of note:**

- All setter methods are returns a new instance with the applied changes.
- For setter methods, Item types are inferred using `Item::from` if a `Item` object is not provided.
