# Building and Updating Fields

## Items value

The defined types are all attached to an `Item` object where their value and
type are accessible using the following methods:

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Type;

$item = Item::fromHttpValue('@1234567890');
$item->type();  // return Type::Date;
$item->value()  // return the equivalent to DateTimeImmutable('@1234567890');
```

The `Item` value object exposes the following named constructors to instantiate
bare items (ie: item without parameters attached to them).

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

Item:new(DateTimeInterface|ByteSequence|Token|DisplayString|string|int|array|float|bool $value): self
Item:tryNew(mixed $value): ?self
Item::fromDecodedByteSequence(Stringable|string $value): self;
Item::fromEncodedDisplayString(Stringable|string $value): self;
Item::fromDecodedDisplayString(Stringable|string $value): self;
Item::fromEncodedByteSequence(Stringable|string $value): self;
Item::fromToken(Stringable|string $value): self;
Item::fromString(Stringable|string $value): self;
Item::fromDate(DateTimeInterface $datetime): self;
Item::fromDateFormat(string $format, string $datetime): self;
Item::fromDateString(string $datetime, DateTimeZone|string|null $timezone = null): self;
Item::fromTimestamp(int $value): self;
Item::fromDecimal(int|float $value): self;
Item::fromInteger(int|float $value): self;
Item::true(): self;
Item::false(): self;
```

To update the `Item` instance value, use the `withValue` method:

```php
use Bakame\Http\StructuredFields\Item;

Item::withValue(DateTimeInterface|ByteSequence|Token|DisplayString|string|int|float|bool $value): static
```

## Containers

All containers objects implement PHP `IteratorAggregate`, `Countable` and `ArrayAccess`
interfaces. Their members can be accessed using the following shared methods

```php
$container->keys(): array<string|int>;
$container->hasIndex(string|int ...$offsets): bool;
$container->getByIndex(int $offset): StructuredField;
$container->isNotEmpty(): bool;
$container->isEmpty(): bool;
```

> [!IMPORTANT]
> The `getByIndex` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.

To avoid invalid states, `ArrayAccess` modifying methods throw a `ForbiddenOperation`
if you try to use them on any container object:

```php
use Bakame\Http\StructuredFields\Parameters;

$value = Parameters::fromHttpValue(';a=foobar');
$value['a']->value(); // return 'foobar'
$value['b'];          // triggers a InvalidOffset exception, the index does not exist
$value['a'] = 23      // triggers a ForbiddenOperation exception
unset($value['a']);   // triggers a ForbiddenOperation exception
```

The `Dictionary` and `Parameters` classes also allow:

- accessing its members as pairs:

```php
$container->hasPair(int ...$offsets): bool;
$container->getByIndex(int $offset): array{0:string, 1:StructuredField};
$container->toPairs(): iterable<array{0:string, 1:StructuredField}>;
```

> [!IMPORTANT]
> The `getByIndex` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.

- accessing its members as value using their key:

```php
$container->hasKey(string ...$offsets): bool;
$container->getByKey(string $offset): StructuredField;
```

> [!IMPORTANT]
> The `getByKey` method will throw an `InvalidOffset` exception if no member exists for the given `$offset`.


#### Accessing the parameters values

Accessing the associated `Parameters` instance attached to an `InnerList` or a `Item` instances
is done using the following methods:

```php
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Parameters;

$field->parameterByKey(string $key): ByteSequence|Token|DisplayString|DateTimeImmutable|Stringable|string|int|float|bool|null;
$field->parameters(): Parameters;
$field->parameterByIndex(int $index): array{0:string, 1:ByteSequence|Token|DisplayString|DateTimeImmutable|Stringable|string|int|float|boo}
InnerList::toPair(): array{0:list<Item>, 1:Parameters}>};
Item::toPair(): array{0:ByteSequence|Token|DisplayString|DateTimeImmutable|Stringable|string|int|float|bool, 1:Parameters}>};
```

> [!NOTE]
> - The `parameterByKey` method returns `null` if no value is found for the given key.
> - The `parameterByIndex` method returns an empty array if no parameter is found for the given index.

### Building and Updating Structured Fields Values

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
`new` named constructor which returns a new instance with no members.vAnd then,
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
```

It is also possible to use the index of each member to perform additional modifications.

```php
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

&larr; [Types](03-types.md)  |  [Validation](05-validation.md) &rarr;
