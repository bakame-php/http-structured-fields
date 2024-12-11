---
layout: default
title: The Structured Field containers Data Types
order: 5
---

# Working with Structured Fields Containers

While building or updating a Bare Item is straightforward, doing the same with structured field containers
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
$permissions->indices();      // returns [0, 1, 2]
$permissions->hasIndices(-2); // returns true because negative index are supported
$permissions->getByIndex(1);  // returns ['geolocation', InnerList::new(Token::fromString('self'), "https://example.com/")]
$permissions['geolocation'];  // returns InnerList::new(Token::fromString('self'), "https://example.com/")
$permissions[1];              // throws a TypeError only string are allowed for Dictionary and Parameters
$permissions->isNotEmpty():;  // returns true
$permissions->isEmpty();      // returns false
```

<p class="message-warning">
The <code>getByIndex</code> method will throw an <code>InvalidOffset</code> exception if no
member exists for the given <code>$offset</code>.
</p>

Here's an example with a `List` container:

```php
$headerLine = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8'
$accepts = OuterList::fromHttpValue($headerLine); // parse the field
$accepts->indices();          // returns [0, 1, 2, 3, 4]
$accepts->hasIndices(-2);     // returns true because negative index are supported
$accepts->getByIndex(1);      // returns Token::fromString('application/xhtml+xml')
$accepts[1];                  // returns Token::fromString('application/xhtml+xml')
$accepts['foo'];              // throws a TypeError only integer are allowed for List and InnerList
$permissions->isNotEmpty():;  // returns true
$permissions->isEmpty();      // returns false
```

<p class="message-warning">
For ordered maps, the <code>getByIndex</code> method returns a list containing exactly 2 entries.
The first entry is the member key, the second entry is the member value. For lists, the method
directly returns the value.
</p>

To avoid invalid states, `ArrayAccess` modifying methods throw a `ForbiddenOperation`
if you try to use them on any container object:

```php
$permissions['picture-in-picture']->isEmpty(); // returns true
$permissions['b'];        // triggers a InvalidOffset exception, the index does not exist
$permissions['a'] = 23    // triggers a ForbiddenOperation exception
unset($permissions['a']); // triggers a ForbiddenOperation exception
```

<p class="message-warning">
For ordered map the <code>ArrayAccess</code> interface will use the member name
whereas for lists the interface will use the member index.
</p>

The `Dictionary` and `Parameters` classes also allow accessing their members as value using their name:

```php
$permissions->hasKey('picture-in-picture');           // returns true
$permissions->hasKey('picture-in-picture', 'foobar'); // returns false 
// 'foobar' is not a valid name or at least it is not present

$permissions->getByKey('camera'); // returns Item::fromToken('*');
$permissions->toAssociative(); // returns an iterator
// the iterator key is the member name and the value is the member value
// the offset is "lost"
$permissions->keyByIndex(42); // returns null because there's no member with the offset 42
$permissions->keyByIndex(2);  // returns 'camera'

$permissions->indexByKey('foobar'): // returns null because there's no member with the name 'foobar'
$permissions->indexByKey('geolocation'): // returns 1
```

<p class="message-warning">
The <code>getByKey</code> method will throw an <code>InvalidOffset</code> exception if no
member exists for the given <code>$offset</code>.
</p>


<ul class="message-info">
<li>The <code>ArrayAccess</code> interface proxy the result from <code>getByIndex</code> and <code>hasIndices</code> with <code>OuterList</code> and <code>InnerList</code>.</li>
<li>The <code>ArrayAccess</code> interface proxy the result from <code>getByKey</code> and <code>hasKeys</code> with <code>Dictionary</code> and <code>Parameters</code>.</li>
</ul>

### Accessing the parameters values

As we have already seen, it is possible to access the `Parameters` values directly
from the `Item` instance. The same public API is used for the `InnerList`.

On the other hand if you already have a `Parameters` instance you can use the
`valueByKey` and `valueByIndex` methods to directly access the value from a single
parameter.

<ul class-="message-info">
<li>The <code>parameterByKey</code> proxy the result from <code>valuerByKey</code>.</li>
<li>The <code>parameterByIndex</code> proxy the result from <code>valuerByIndex</code>.</li>
</ul>

## Building and Updating Containers

Every container can be used as a builder to create an HTTP field value. Because we are
using immutable value objects any change to the value object will return a new instance
with the changes applied and leave the original instance unchanged.

### Ordered Maps

The `Dictionary` and `Parameters` are ordered map instances. They can be built using their names with an
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

or using their indices with an iterable structure of pairs as defined in the RFC:

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
$map->insert(int $index, array ...$pairs): static;
$map->replace(int $index, array $pair): static;
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

<p class="message-warning">on duplicate <code>names</code> pair values are merged as per RFC logic.</p>


The following methods `removeByIndices` and/or `removeByKeys` allow removing members
per indices or per names.

```php
use Bakame\Http\StructuredFields\Parameters;

$field = Parameters::fromHttpValue(';expire=@1681504328;path="/";max-age=2500;secure;httponly=?0;samesite=lax');
echo $field->removeByIndices(4, 2, 0)->toHttpValue();                      // returns ;path="/";secure;samesite=lax
echo $field->removeByKeys('expire', 'httponly', 'max-age')->toHttpValue(); // returns ;path="/";secure;samesite=lax
```

### Automatic conversion

Learning new types may be a daunting tasks so for ease of usage, all datatype can be represented using an array as list.
The automatic conversion are applied on the member argument of each modifying methods>

If the submitted type is:

-  one of the five Data type implementing object, it will be passed as is
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
use Bakame\Http\StructuredFields\Bytes;

$list = InnerList::new(
    Bytes::fromDecoded('Hello World'),
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
$list->insert(int $index, ...$members): static;
$list->replace(int $index, $member): static;
$list->removeByIndices(int ...$index): static;
```

as shown below

```php
use Bakame\Http\StructuredFields\Bytes;
use Bakame\Http\StructuredFields\InnerList;

$list = InnerList::new()
    ->unshift('42')
    ->push(42)
    ->insert(1, 42.0)
    ->replace(0, Bytes::fromDecoded('Hello World'));

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
use Bakame\Http\StructuredFields\Bytes;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

//@type SfItemInput Byte|Token|DateTimeInterface|string|int|float|bool

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
$field->withParameters(Parameters $parameters): static;
$field->addParameter(string $key, mixed $value): static;
$field->appendParameter(string $key, mixed $value): static;
$field->prependParameter(string $key, mixed $value): static;
$field->pushParameters(array ...$pairs): static
$field->unshiftParameters(array ...$pairs): static
$field->insertParameters(int $index, array ...$pairs): static
$field->replaceParameter(int $index, array $pair): static
$field->withoutParametersByKeys(string ...$keys): static
$field->withoutParametersByIndices(int ...$indices): static
$field->withoutAnyParameter(): static;
```

The `$pair` parameter is an array as list with exactly two members where:

- the first array member is the parameter `$key`
- the second array member is the parameter `$value`

<p class="message-warning">The return value will be the parent class an NOT a <code>Parameters</code>` instance</p>

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

To ease, usage and validation all containers exposes the `map`, `reduce`, `filter` and `sort` methods.
The methods leverage the result of the `foreach` loop on each container. While the `filter` and `sort` method
will return a new container instance, the `map` method returns an `Iterator`.

Last but not least, all datatypes exposes the conditional `when` method to improve building the structured field.
This can be helpful if fdr instance your input value would otherwise trigger an exception.

In the example below we are conditionally building an `Item` depending on the data found in the
`$cache` value object. This is also possible for all containers.

```php
Item::new($cache->name)
    ->when($cache->hit, fn (Item $item) => $item->appendParameter('hit', $cacher->hit))
    ->when(null !== $cache->ttl, fn (Item $item) => $item->appendParameter('ttl', $cache->ttl)) 
    ->when(null !== $cache->key, fn (Item $item) => $item->appendParameter('key', $cache->key)) 
    ->when(null !== $cache->detail, fn (Item $item) => $item->appendParameter('detail', $cache->detail));
```
