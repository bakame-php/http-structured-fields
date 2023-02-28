Parsing and Serializing Structured Fields
------------

To parse an HTTP field you may use the `fromHttpValue` named constructor provided by all the
immutable value objects:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\OuterList;

$dictionary = Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
$item = Item::fromHttpValue('"foo";a=1;b=2');
$innerList = InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("');
$list = OuterList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
$parameters = Parameters::fromHttpValue(';foo=bar');
```

The `fromHttpValue` named constructor returns an instance of the `Bakame\Http\StructuredFields\StructuredField` interface
which provides a way to serialize back the object into a normalized RFC compliant HTTP field using the `toHttpValue` method.

To ease integration with current PHP frameworks and packages working with HTTP headers and trailers, each value object
also exposes the `Stringable` interface method `__toString` which is an alias of the `toHttpValue` method.

````php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Value;

$dictionary = Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
echo $dictionary->toHttpValue(); // 'a=?0, b, c;foo=bar'
echo $dictionary;                // 'a=?0, b, c;foo=bar'

$list = OuterList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
echo $list->toHttpValue(); // '("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1'
echo $list;                // '("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1'

$item = Item::fromHttpValue('"foo";a=1;b=2');
echo $item->toHttpValue(); // "foo";a=1;b=2
echo $item;                // "foo";a=1;b=2

$parameters = Parameters::fromHttpValue('      ;foo=bar');
echo $parameters->toHttpValue(); // ;foo=bar
echo $parameters;                // ;foo=bar
````

Building and Updating Structured Fields 
------------

Updating or creating an HTTP field value can be achieved using any of our immutable value object as a starting point:

The `Dictionary` and the `Parameters` objects can be instantiated using the following named constructors:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Parameters;

Dictionary::fromAssociative($associativeEnumerable): self; // from associative iterable
Parameters::fromAssociative($associativeEnumerable): self; // from associative iterable

Dictionary::fromPairs($enumerableTuple): self; // from pairs as iterable tuple
Parameters::fromPairs($enumerableTuple): self; // from pairs as iterable tuple

Dictionary::create(): self; // an empty container
Parameters::create(): self; // an empty container
```

The `OuterList` and the `InnerList` objects can be instantiated using the following named constructors:

```php
use Bakame\Http\StructuredFields\OuterList;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\StructuredFields;

OuterList::from(...$members): self; // from a list of structured fields
InnerList::from(...$members): self; // from a list of structured fields

OuterList::fromList($members): self; // from an iterable list of structured fields
InnerList::fromList($members, $parameters): self; // / from an iterable list of structured fields and of parameters
```

The `Item` value object can be instantiated using the following named constructors:

```php
use Bakame\Http\StructuredFields\Item;

Item::from($value, $parameters): self;                    // from a value and an associate iterable of parameters
Item::fromToken($value, $parameters): self;               // a string to convert to a Token and an associate iterable of parameters
Item::fromDecodedByteSequence($value, $parameters): self; // a string to convert to a Byte Sequence and an associate iterable of parameters
Item::fromEncodedByteSequence($value, $parameters): self; // a raw string of encoded Byte Sequence and an associate iterable of parameters
Item::fromPair([$value, $parameters]): self               // from a tuple of a value and a pair iterable of parameters
```

The RFC define two (2) specific data types that can not be represented by PHP default type system, for them, we define
two classes `Token` and `ByteSequence` to help representing them in our code.

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;

Token::fromString(string|Stringable $value): self;       // from a value and an associate iterable of parameters
ByteSequence::fromDecoded($value): self;                  // a string to convert to a Token and an associate iterable of parameters
ByteSequence::fromEncoded($value): self;                  // a string to convert to a Byte Sequence and an associate iterable of parameters
```

With this API in mind, it then becomes possible to do the following:

```php
use Bakame\Http\StructuredFields\Dictionary;

$value = Dictionary::fromAssociative([
    'b' => false,
    'a' => Item::fromToken('bar', ['baz' => 42]),
    'c' => new DateTimeImmutable('2022-12-23 13:00:23'),
]);

echo $value->toHttpValue(); //"b=?0, a=bar;baz=42;c=@1671800423"
echo $value;  //"b=?0, a=bar;baz=42;c=@1671800423"
```

Because HTTP fields value are defined via tuples, the same result can be achieved using pairs:

```php
use Bakame\Http\StructuredFields\Dictionary;

$dicTuple = Dictionary::fromPairs([
    ['b', false],
    ['a', Item::fromPair([
        Token::fromString('bar'),
        [['baz', 42]]
    ])],
    ['c', new DateTime('2022-12-23 13:00:23')]
]);

echo $dicTuple->toHttpValue(); //"b=?0, a=bar;baz=42;c=@1671800423"
echo $dicTuple;  //"b=?0, a=bar;baz=42;c=@1671800423"
```

If builder methods are preferred, the same result can be achieved with the following steps:

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Token;

$bar = Item::from(Token::fromString('bar'))
    ->appendParameter('baz', Item::from(42));
$dictBuilder = Dictionary::create()
    ->append('b', Item::from(false))
    ->append('a', $bar)
    ->append('c', Item::from(new DateTimeImmutable('2022-12-23 13:00:23')))
;

echo $dictBuilder->toHttpValue(); //"b=?0, a=bar;baz=42;c=@1671800423"
echo $dictBuilder;  //"b=?0, a=bar;baz=42;c=@1671800423"
```

In every scenario if the Data Type can be inferred from the PHP type it will get converted into it's
correct data type behind the scene. It is possible to mix the different style if it suits the usage.

It is possible to get more information on each value object using the following links:

- an [Item](item.md);
- a [Dictionary](ordered-maps.md);
- a [List](lists.md);
