# Containers

```php
use Bakame\Http\StructuredFields;

$parameters = StructuredFields\Parameters::fromAssociative(['a' => 1, 'b' => 2, 'c' => "hello world"]);
count($parameters); // returns 3
$parameters->hasMembers(); // returns true
$parameters->toHttpValue(); // returns ';a=1;b=2;c="hello world"'
$parameters->clear()->hasNoMembers(); // returns true
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
- access container members via the `ArrayAccess` interface;
- tell whether the container contains or not members with the `hasNoMembers` and/or `hasMembers` methods from the `MemberContainer` interface;
- clear its content using the `clear` method from the `MemberContainer` interface;
- unwrap Item value using the `values`, `value` methods from the `MemberContainer` interface;

getter methods:

- `get` to access an element at a given index (negative indexes are supported)
- `has` tell whether an element is attached to the container using its `index`;

**Of note:**

- All setter methods are chainable
- For setter methods, Item types are inferred using `Item::from` if a `Item` object is not provided.
