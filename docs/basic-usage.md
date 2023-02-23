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

