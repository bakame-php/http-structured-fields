# Containers

While building or updating a Bare Item is straightforward, doing the same with the structured field
requires a bit more logic. In the following sections we will explore how we can access, build and update
containers.

## Accessing Containers members

All containers objects implement PHP `IteratorAggregate`, `Countable` and `ArrayAccess`
interfaces. Their members can be accessed using the following shared methods

If we go back to our permission policy field example:

```php
$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
//the raw header line is a structured field dictionary
$permissions = Dictionary::fromHttpValue($headerLine); // parse the field
```

The following methods are available, for all containers:

```php
$permissions->indices();      // returns [0, 1, 2]
$permissions->hasIndices(-2); // returns true bacause negative index are supported
$permissions->getByIndex(1);  // returns ['geolocation', InnerList::new(Token::fromString('self'), "https://example.com/")]
$permissions->isNotEmpty():;  // returns true
$permissions->isEmpty();      // returns false
```

> [!IMPORTANT]
> The `getByIndex` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.

> [!IMPORTANT]
> For ordered maps, the `getByIndex` method returns a list containing exactly 2 entries.
> The first entry is the member key, the second entry is the member value.
> For lists, the method directly returns the value.

To avoid invalid states, `ArrayAccess` modifying methods throw a `ForbiddenOperation`
if you try to use them on any container object:

```php
$permissions['picture-in-picture']->isEmpty(); // returns true
$permissions['b'];        // triggers a InvalidOffset exception, the index does not exist
$permissions['a'] = 23    // triggers a ForbiddenOperation exception
unset($permissions['a']); // triggers a ForbiddenOperation exception
```

> [!IMPORTANT]
> For ordered map the ArrayAccess interface will use the member key
> whereas for lists the interface will use the member index.

The `Dictionary` and `Parameters` classes also allow accessing its members as value using their key:

```php
$permissions->hasKey('picture-in-picture');           // returns true
$permissions->hasKey('picture-in-picture', 'foobar'); // returns false 
// 'foobar' is not a valid key or at least it is not present

$permissions->getByKey('camera'); // returns Item::fromToken('*');
$permissions->toAssociative(); // returns an iterator
// the iterator key is the member key and the value is the member value
// the offset is "lost"
$permissions->keyByIndex(42); // returns null because there's no member with the offset 42
$permissions->keyByIndex(2);  // returns 'camera'

$permissions->indexByKey('foobar'): // returns null because there's no member with the key 'foobar'
$permissions->indexByKey('geolocation'): // returns 1
```

> [!IMPORTANT]
> The `getByKey` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.

> [!TIP]
> The `ArrayAccess` interface proxy the result from `getByIndex` in list.
> The `ArrayAccess` interface proxy the result from `getByKey` in ordered map.

### Accessing the parameters values

As we have already seen, it is possible to access the `Parameters` values directly
from the `Item` instance. The same public API is used from the `InnerList`.

On the other hand if you already have a `Parameters` instance you can use the
`valueByKey` and `valueByIndex` methods to directly access the value from a single
parameter.

> [!TIP]
> The `parameterByKey` proxy the result from `valueByKey`.
> The `parameterByIndex` proxy the result from `valueByIndex`.

## Container validations

### Parameters validation

We have already seen when validating the `Item` the `ParametersValidator` class. Apart from it, all the
ordered map exposes the `allowedKeys` method which returns true if all the parameters present in the `Parameters`
uses one of the submitted key.

```php
$permissions->allowedKeys('picture-in-picture', 'geolocation', 'camera', 'foobar'); //returns true
// even thought the `foobar` key is not used it is allowed.
$permissions->allowedKeys('picture-in-picture', 'camera'); //returns false
// the 'geolocation' key is present but not allowed in the list!
```

This method can be used by the `ParametersValidator::filterByCriteria` method if needed.

### Value validation

`getByIndex` and `getByKey` method accept an optional callable `validate` method. This method can be used 
to validate the expected returned value.

```php
$permissions->getByKey('geolocation', function (mixed $member) {
    // add your validation rules heres 
    // or create a separate invokable class
});

$permissions->getByIndex(1, function (mixed $member, string $key) {
    // add your validation rules heres 
    // or create a separate invokable class
});
```

## Building and Updating Structured Fields Values

Every value object can be used as a builder to create an HTTP field value. Because we are
using immutable value objects any change to the value object will return a new instance
with the changes applied and leave the original instance unchanged.

### Ordered Maps

The `Dictionary` and `Parameters` are ordered map instances. They can be built using their keys with an
associative iterable structure as shown below

```php
use Bakame\Http\StructuredFields\Dictionary;

$value = Dictionary::fromAssociative([
    'b' => Item::false(),
    'a' => Item::fromToken('bar'),
    'c' => new DateTimeImmutable('2022-12-23 13:00:23'),
]);

echo $value->toHttpValue(); //"b=?0, a=bar, c=@1671800423"
echo $value;                //"b=?0, a=bar, c=@1671800423"
```

or using their indices with an iterable structure of pairs (tuple) as defined in the RFC:

```php
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\Item;

$value = Parameters::fromPairs(new ArrayIterator([
    ['b', Item::false()],
    ['a', Item::fromToken('bar')],
    ['c', new DateTime('2022-12-23 13:00:23')]
]));

echo $value->toHttpValue(); //;b=?0;a=bar;c=@1671800423
echo $value;                //;b=?0;a=bar;c=@1671800423
```

If the preference is to use the builder pattern, the same result can be achieved with the
following steps. You, first, create a `Parameters` or a `Dictionary` instance using the
`new` named constructor which returns a new instance with no members. And then,
use any of the following modifying methods to populate it.

```php
$map->add(string $key, $value): static;
$map->append(string $key, $value): static;
$map->prepend(string $key, $value): static;
$map->mergeAssociative(...$others): static;
$map->removeByKeys(string ...$keys): static;
```

As shown below:
`
```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$value = Dictionary::new()
    ->add('a', InnerList::new(
        Item::fromToken('bar'),
        Item::fromString('42'),
        Item::fromInteger(42),
        Item::fromDecimal(42)
     ))
    ->prepend('b', Item::false())
    ->append('c', Item::fromDateString('2022-12-23 13:00:23'))
;

echo $value->toHttpValue(); //b=?0, a=(bar "42" 42 42.0), c=@1671800423
echo $value;                //b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

It is possible to also build `Dictionary` and `Parameters` instances
using indices and pair as described in the RFC.

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

```php
$map->unshift(array ...$pairs): static;
$map->push(array ...$pairs): static;
$map->insert(int $key, array ...$pairs): static;
$map->replace(int $key, array $pair): static;
$map->mergePairs(...$others): static;
$map->removeByIndices(int ...$indices): static;
```

We can rewrite the previous example

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$value = Dictionary::new()
    ->push(
        ['a', InnerList::new(
            Item::fromToken('bar'),
            Item::fromString('42'),
            Item::fromInteger(42),
            Item::fromDecimal(42)
         )],
         ['c', Item::true()]
     )
    ->unshift(['b', Item::false()])
    ->replace(2, ['c', Item::fromDateString('2022-12-23 13:00:23')])
;

echo $value->toHttpValue(); //b=?0, a=(bar "42" 42 42.0), c=@1671800423
echo $value;                //b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

> [!CAUTION]
> on duplicate `keys` pair values are merged as per RFC logic.

The following methods `removeByIndices` and/or `removeByKeys` allow removing members
per indices or per keys.

```php
use Bakame\Http\StructuredFields\Parameters;

$field = Parameters::fromHttpValue(';expire=@1681504328;path="/";max-age=2500;secure;httponly=?0;samesite=lax');
echo $field->removeByIndices(4, 2, 0)->toHttpValue();                      // returns ;path="/";secure;samesite=lax
echo $field->removeByKeys('expire', 'httponly', 'max-age')->toHttpValue(); // returns ;path="/";secure;samesite=lax
```

### Automatic conversion

For all containers, to ease instantiation the following automatic conversion are applied on
the member argument of each modifying methods.

If the submitted type is:

-  a `StructuredField` implementing object, it will be passed as is
-  an iterable structure, it will be converted to an `InnerList` instance using `InnerList::new`
-  otherwise, it is converted into an `Item` using the `Item::new` named constructor.

If no conversion is possible an `InvalidArgument` exception will be thrown.

This means that both constructs below built equal objects

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

echo Dictionary::new()
    ->add('a', InnerList::new(
        Item::fromToken('bar'),
        Item::fromString('42'),
        Item::fromInteger(42),
        Item::fromDecimal(42)
     ))
    ->prepend('b', Item::false())
    ->append('c', Item::fromDateString('2022-12-23 13:00:23'))
    ->toHttpValue()
;

echo Dictionary::new()
    ->add('a', [Token::fromString('bar'), '42', 42, 42.0])
    ->prepend('b', false)
    ->append('c', new DateTimeImmutable('2022-12-23 13:00:23'))
    ->toHttpValue()
;

 // both will return 'b=?0, a=(bar "42" 42 42.0), c=@1671800423
```

Of course, it is possible to mix both notations.

### Lists

To create `OuterList` and `InnerList` instances you can use the `new` named constructor
which takes a single variadic parameter `$members`:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\ByteSequence;

$list = InnerList::new(
    ByteSequence::fromDecoded('Hello World'),
    42.0,
    42
);

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

Once again, the builder pattern can be used via a combination of the `new`
named constructor and the use any of the following modifying methods.

```php
$list->unshift(...$members): static;
$list->push(...$members): static;
$list->insert(int $key, ...$members): static;
$list->replace(int $key, $member): static;
$list->removeByIndices(int ...$key): static;
```

as shown below

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\InnerList;

$list = InnerList::new()
    ->unshift('42')
    ->push(42)
    ->insert(1, 42.0)
    ->replace(0, ByteSequence::fromDecoded('Hello World'));

echo $list->toHttpValue(); //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $list;                //'(:SGVsbG8gV29ybGQ=: 42.0 42)'
```

It is also possible to create an `OuterList` based on an iterable structure
of pairs.

```php
use Bakame\Http\StructuredFields\OuterList;

$list = OuterList::fromPairs([
    [
        ['foo', 'bar'],
        [
            ['expire', new DateTime('2024-01-01 12:33:45')],
            ['path', '/'],
            [ 'max-age', 2500],
            ['secure', true],
            ['httponly', true],
            ['samesite', Token::fromString('lax')],
        ]
    ],
    [
        'coucoulesamis', 
        [['a', false]],
    ]
]);
```

The pairs definitions are the same as for creating either a `InnerList` or an `Item` using
their respective `fromPair` method.

### Adding and updating parameters

To ease working with instances that have a `Parameters` object attached to, the following
methods are added:

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

//@type SfItemInput ByteSequence|Token|DateTimeInterface|string|int|float|bool

Item::fromAssociative(SfItemInput $value, Parameters|iterable<string, SfItemInput> $parameters): self;
Item::fromPair(array{0:SfItemInput, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>} $pair): self;

InnerList::fromAssociative(iterable<SfItemInput> $value, Parameters|iterable<string, SfItemInput> $parameters): self;
InnerList::fromPair(array{0:iterable<SfItemInput>, Parameters|iterable<array{0:string, 1:SfItemInput}>} $pair): self;
```

The following example illustrate how to use those methods:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;

echo Item::fromAssociative(
        Token::fromString('bar'),
        ['baz' => 42]
    )->toHttpValue(), PHP_EOL;

echo Item::fromPair([
        Token::fromString('bar'),
        [['baz', 42]],
    ])->toHttpValue(), PHP_EOL;

//both methods return `bar;baz=42`
```

Both objects provide additional modifying methods to help deal with parameters.
You can attach and update the associated `Parameters` instance using the following methods.

```php
$field->addParameter(string $key, mixed $value): static;
$field->appendParameter(string $key, mixed $value): static;
$field->prependParameter(string $key, mixed $value): static;
$field->withoutParameters(string ...$keys): static; // this method is deprecated as of version 1.1 use withoutParametersByKeys instead
$field->withoutAnyParameter(): static;
$field->withParameters(Parameters $parameters): static;
$field->pushParameters(array ...$pairs): static
$field->unshiftParameters(array ...$pairs): static
$field->insertParameters(int $index, array ...$pairs): static
$field->replaceParameter(int $index, array $pair): static
$field->withoutParametersByKeys(string ...$keys): static
$field->withoutParametersByIndices(int ...$indices): static
```

The `$pair` parameter is a tuple (ie: an array as list with exactly two members) where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

> [!WARNING]
> The return value will be the parent class an NOT a `Parameters` instance

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;

echo InnerList::new('foo', 'bar')
    ->addParameter('expire', Item::fromDateString('+30 minutes'))
    ->addParameter('path', '/')
    ->addParameter('max-age', 2500)
    ->toHttpValue();

echo InnerList::new('foo', 'bar')
    ->pushParameter(
        ['expire', Item::fromDateString('+30 minutes')],
        ['path', '/'],
        ['max-age', 2500],
    )
    ->toHttpValue();

// both flow return the InnerList HTTP value 
// ("foo" "bar");expire=@1681538756;path="/";max-age=2500
```

&larr; [Item](04-item.md)  |  [Validation](06-validation.md) &rarr;
