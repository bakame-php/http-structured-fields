Parsing and Serializing Structured Fields
------------

an HTTP field value can be:

- an [Item](item.md);
- a [Dictionary](ordered-maps.md);
- a [List](lists.md);

For each of these top-level types, the package provides a dedicated object to parse the textual
representation of the field and to serialize the value object back to the textual representation.

- Parsing is done via a common named constructor `fromHttpValue` which expects the Header or Trailer string value.
- Serializing is done via a common `toHttpValue` public method or using the `__toString` method. The method returns the **normalized string** representation suited for HTTP textual representation.

```php
use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OuterList;

$dictionary = Dictionary::fromHttpValue("a=?0,   b,   c=?1; foo=bar");
echo $dictionary->toHttpValue(); // 'a=?0, b, c;foo=bar'
echo $dictionary;                // 'a=?0, b, c;foo=bar'

$list = OuterList::fromHttpValue('("foo"; a=1;b=2);lvl=5, ("bar" "baz");lvl=1');
echo $list->toHttpValue(); // '("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1'
echo $list;                // '("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1'

$item = Item::fromHttpValue('"foo";a=1;b=2');
echo $item->toHttpValue(); // "foo";a=1;b=2
echo $item;                // "foo";a=1;b=2
```

Building and Updating Structured Fields 
------------

Updating or creating an HTTP field value can be achieved using any of our immutable value object as a starting point:

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
