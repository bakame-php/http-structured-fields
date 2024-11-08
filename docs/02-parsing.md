# Parsing and Serializing HTTP Fields

## Parsing fields

Processing HTTP Fields has never been easy as it was never standardized before. With structured fields this aspect
of processing HTTP fields is made predicable you will not have to re-invent the wheel for each new fields.
To do so the RFC defines 3 main structures an HTTP fields can have. It can be a `List`, a `Dictionary` or 
an `Item`. By specifying that your field is one of those structure you already gave the author the complete
implementation of the field or at least the way it should be parsed.

### Structured Fields Data Types

If we go back to our first example, the permission policy field, it is defined as a `Dictionary` as such the
package will use the `Dictionary` parsing process to split the field accordingly.

```php
use Bakame\Http\StructuredFields\Dictionary;

$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
// can also be written as follows
$permission = Dictionary::fromHttpValue($headerLine);
```

The `Dictionary` class is an implementation of the structured field `Dictionary` data type. The package
provides a PHP implementation for each data type supported by the RFC. The following table summarizes
the data type system.

| RFC Type      | PHP Type                  | Package Enum Name      | Package Enum Value |
|---------------|---------------------------|------------------------|--------------------|
| List          | class `OuterList`         | `DataType::List`       | `list`             | 
| Dictionary    | class `Dictionary`        | `DataType::Dictionary` | `dictionary`       | 
| Item          | class `Item`              | `DataType::Item`       | `item`             |
| InnerList     | class `InnerList`         | `DataType::InnerList`  | `innerlist`        | 
| Parameters    | class `Parameters`        | `DataType::Parameters` | `parameters`       |

Apart from the `Item` all the other types are containers. But all the classes share the same
method for parsing the HTTP text representation of the header via the `fromHttpValue` named 
constructor. This method will parse the field string representation and return an instantiated
PHP class containing the parsing result. Because there's 2 RFC related to structured fields,
the method accepts an optional enum  `Ietf` that indicates which RFC should be used for 
conformance. If not enum value is provided, the method will fall back to using the latest
accepted RFC which is at the moment of writing `RFC9651`.

```php
use Bakame\Http\StructuredFields\OuterList;
use Bakame\Http\StructuredFields\Ietf;

$headerLine = '(), (self "https://example.com/");start=@123456789'; 
$field = OuterList::fromHttpValue($headerLine, Ietf::Rfc9651); 
// will work 
$field = OuterList::fromHttpValue($headerLine, Ietf::Rfc8941); 
// will trigger a SyntaxError because the field syntax is invalid for RFC8941
```

Each construct also provides two syntactic sugar methods, the `fromRfc9651` and `fromRfc8941`
named constructors, if you are more comfortable not using the `Ietf` enum.

```php
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Ietf;

$headerLine = '"https://example.com/";start=@123456789'; 
$field = Item::fromRfc9651($headerLine); 
// will work 
$field = Item::fromRfc8941($headerLine); 
// will trigger a SyntaxError because the field syntax is invalid for RFC8941
```

> [!TIP]
> The `DataType::parse` method uses the `fromHttpValue` named constructor for
> each specific class to generate the structured field PHP representation.

### Data Types relationships

Once parsed, each data type will expose its content depending on its specification. What's
important to remember is that apart from the `Item` all the other data types are containers.
As such their data can be accessible via their indices (for all containers) and also via
their member name if the container represents an ordered map. The relation between each
construct can be summarized as follows:

- `Dictionary`and `OuterList` instances can only contain `Item` and `InnerList`;
- `InnerList`and `Parameters` instances can only contain `Item`;
- `OuterList` and `InnerList` members can only be accessed by their indices;
- `Dictionary` and `Parameters` members can also be accessed by their name;
- `Item` and `InnerList` instancs can have a `Parameters` container attached to.
- `Item` contain in a `Parameters` container **can not have** parameters attached to them to avoid recursion. They are named **Bare Item**.
- `Item` contain in a `InnerList` container **can have** parameters attached to them.

Let's use simple examples to illustrate those rules:

```php
use Bakame\Http\StructuredFields\DataType;

$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
$permissions = DataType::Dictionary->parse($headerLine); // parse the dictionary structured field
$permissions['picture-in-picture']->isEmpty(); // picture-in-picture returned value is an empty InnerList
count($permissions['geolocation']);            // geolocation returned value is an InnerList of 3 Items
$permissions['geolocation'][1]->value();       // returns "https://example.com/"
$permissions['camera']->value();               // camera only value is a single Item
$permissions->getByIndex(2);                   // returns a tuple as n array ['camera', Item::fromPair(['*', []])]
```

In the example above:

- We have no `Parameters` class used, 
- We see that a `Dictionary` field only contains `Item` and `InnerList` containers
- the `InnerList` contains solely `Items` that can be accessible via their indices
- the `Dictionary` values can be accessible via their names or thir indices.

The following field is an example from the Accept header which is already structured fields compliant.

```php
$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$field = DataType::List->parse($fieldValue);
$field[2]->value()->toString(); // returns 'application/xml'
$field[2]->parameterByKey('q'); // returns (float) 0.9
$field[0]->value()->toString(); // returns 'text/html'
$field[0]->parameterByKey('q'); // returns null
```

- The Accept header is an `List` field made of `Item` only.
- each `Item` can have an attached `Parameters` container
- Member of the `Parameters` container can be accessed by named from the `Item` they are attached to.

## Serializing fields

Each Data type implementation is able to convert itself into a proper HTTP text representation
using its `toHttpValue` method. The method is complementary to the `fromHttpValue` named constructor
in that it does the opposite of that method. Just like the `fromHttpValue`, it can take an optional
`Ietf` enum to specifu which RFC version it should adhere to for serialization. And two syntactic
methods `toRfc9651` and `toRfc8941` are also present to ease usage. Last but not least All classes
implements the `Stringable` interface using the latest accepted RFC.

```php
$fieldValue = 'text/html,    application/xhtml+xml,   application/xml;q=0.9,   image/webp,   */*;q=0.8';
$field = DataType::List->parse($fieldValue);
$field->toHttpValue();
(string) $field;
// both calls will return
// 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
```

The method applies by default all the normalizations transformation recommended by the RFC. 

> [!TIP]
> This is the mechanism used by the `DataType::serialize` method. Once the Structured
> field has been created, the method calls its `toHttpValue` method.

&larr; [Basic Usage](01-basic-usage.md)  |  [Value Types](03-types.md) &rarr;
