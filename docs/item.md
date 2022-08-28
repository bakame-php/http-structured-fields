Items
----

## Definitions

Items can have different types that are translated to PHP using:

- native type where possible
- specific classes defined in the package namespace to represent non-native type

The table below summarizes the item value type.

| HTTP DataType | Package Data Type    | validation method      |
|---------------|----------------------|------------------------|
| Integer       | `int`                | `Item::isInteger`      |
| Decimal       | `float`              | `Item::isDecimal`      |
| String        | `string`             | `Item::isString`       |
| Boolean       | `bool`               | `Item::isBoolean`      |
| Token         | class `Token`        | `Item::isToken`        |
| Byte Sequence | class `ByteSequence` | `Item::isByteSequence` |

### Token

```php
use Bakame\Http\StructuredFields;

$token = StructuredFields\Token::fromString('bar');

echo $token->value;              //displays 'bar'
echo $dictionary->toHttpValue(); //displays 'bar'
```

The Token data type is a special string as defined in the RFC. To distinguish it from a normal string,
the `Bakame\Http\StructuredFields\Token` class is used.

To instantiate the class you are required to use the `Token::fromString` named constructor.
The class also exposes its value via the public readonly property `value` to enable its textual representation.

### Byte Sequence

```php
use Bakame\Http\StructuredFields;

$sequenceFromDecoded = StructuredFields\ByteSequence::fromDecoded("Hello World");
$sequenceFromEncoded = StructuredFields\ByteSequence::fromEncoded("SGVsbG8gV29ybGQ=");

echo $sequenceFromEncoded->decoded(); //displays 'Hello World'
echo $sequenceFromDecoded->encoded(); //displays 'SGVsbG8gV29ybGQ='
```

The Byte Sequence data type is a special string as defined in the RFC to represent base64 encoded data.
To distinguish it from a normal string, the `Bakame\Http\StructuredFields\ByteSequence` class is used.

To instantiate the class you are required to use the `ByteSequence::fromDecoded` or `ByteSequence::fromEncoded`
named constructors. The class also exposes the complementary public methods `ByteSequence::decoded`,
`ByteSequence::encoded`  to enable its textual representation.

## Usages

Items can be associated with an ordered maps of key-value pairs also known as parameters, where the
keys are strings and the value are bare items. Their public API is covered in the [ordered maps section](ordered-maps.md].

**An item without any parameter associated to it is said to be a bare item.**

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::from("hello world", ["a" => true]);
$item->value;      //returns "hello world"
$item->isString(); //returns true
$item->isToken();  //returns false
$item->parameters->value("a"); //returns true
```

Instantiation via type recognition is done using the `Item::from` named constructor.

- The first argument represents one of the six (6) item type value;
- The second argument, which is optional, MUST be an iterable construct  
  where its index represents the parameter key and its value an item or an item type value;

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::fromPair([
    "hello world", 
    [
        ["a", StructuredFields\ByteSequence::fromDecoded("Hello World")],
    ]
]);
$item->value; //returns "hello world"
$item->isString(); //return true
$item->parameters->get("a")->isByteSequence(); //returns true
$item->parameters->value("a"); //returns the decoded value 'Hello World'
echo $item->toHttpValue();     //returns "hello world";a=:SGVsbG8gV29ybGQ=:
```

`Item::fromPair` is an alternative to the `Item::from` named constructor, it expects
a tuple composed by an array as a list where:

- The first member on index `0` represents one of the six (6) item type value;
- The second optional member, on index `1`, **MUST** be an iterable construct containing
  tuples of key-value pairs;

Once instantiated, accessing `Item` properties is done via two (2) readonly properties:

- `Item::value` which returns the instance underlying value
- `Item::parameters` which returns the parameters associated to the `Item` as a distinct `Parameters` object

And on method called `Item::decodedValue()` which returns the underlying value fully decoded.

```php
use Bakame\Http\StructuredFields;

$item = StructuredFields\Item::from(StructuredFields\ByteSequence::fromEncoded("SGVsbG8gV29ybGQ=")]);
$item->value; //returns instance of StructuredFields\ByteSequence object
$item->isByteSequence(); //returns true
$item->decodedValue(); //returns the decoded value 'Hello World'
echo 

**Of note: to instantiate a decimal number type a float MUST be used as the first argument of `Item::from`.**

```php
use Bakame\Http\StructuredFields;

$decimal = StructuredFields\Item::from(42.0);
$decimal->isDecimal(); //return true
$decimal->isInteger(); //return false

$item = StructuredFields\Item::fromPair([42]);
$item->isDecimal(); //return false
$item->isInteger(); //return true
```
