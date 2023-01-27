Items
----

## Definitions

Items can have different types that are translated to PHP using:

- native type where possible
- specific classes defined in the package namespace to represent non-native type

The table below summarizes the item value type.

| HTTP DataType | Package Data Type         | Package Enum Type    |
|---------------|---------------------------|----------------------|
| Integer       | `int`                     | `Type::Integer`      |
| Decimal       | `float`                   | `Type::Decimal`      |
| String        | `string`                  | `Tyoe::String`       |
| Boolean       | `bool`                    | `Type::Boolean`      |
| Token         | class `Token`             | `Type::Token`        |
| Byte Sequence | class `ByteSequence`      | `Type::ByteSequence` |
| Date          | class `DateTimeImmutable` | `Type::Date`         |

### Token

```php
use Bakame\Http\StructuredFields\Token;

$token = Token::fromString('bar');

echo $token->value; // displays 'bar'
```

The Token data type is a special string as defined in the RFC. To distinguish it from a normal string,
the `Bakame\Http\StructuredFields\Token` class can be used.

To instantiate the class you are required to use the `Token::fromString` named constructor.
The class also exposes its value via the public readonly property `value` to enable its textual representation.

### Byte Sequence

```php
use Bakame\Http\StructuredFields\ByteSequence;

$sequenceFromDecoded = ByteSequence::fromDecoded("Hello World");
$sequenceFromEncoded = ByteSequence::fromEncoded("SGVsbG8gV29ybGQ=");

echo $sequenceFromEncoded->decoded(); // displays 'Hello World'
echo $sequenceFromDecoded->encoded(); // displays 'SGVsbG8gV29ybGQ='
```

The Byte Sequence data type is a special string as defined in the RFC to represent base64 encoded data.
To distinguish it from a normal string, the `Bakame\Http\StructuredFields\ByteSequence` class is used.

To instantiate the class you are required to use the `ByteSequence::fromDecoded` or `ByteSequence::fromEncoded`
named constructors. The class also exposes the complementary public methods `ByteSequence::decoded`,
`ByteSequence::encoded` to enable the correct textual representation.

## Usages

Items can be associated with parameters that are an ordered maps of key-value pairs where the
keys are strings and the value are bare items. Their public API is covered in the [ordered maps section](ordered-maps.md].

**An item without any parameter associated to it is said to be a bare item.**
**Exception will be thrown when trying to access or serialize a parameter object containing non-bare items.**

```php
use Bakame\Http\StructuredFields\Item;

$item = Item::from("hello world", ["a" => true]);
$item->value(); // returns "hello world"
$item->type();  // returns Type::String
$item->parameters()['a']->value(); //returns true
```

Instantiation via type recognition is done using the `Item::from` named constructor.

- The first argument represents one of the six (6) item type value;
- The second argument, which is optional, MUST be an iterable construct  
  where its index represents the parameter key and its value an item or an item type value;

It is possible to use

- `Item::fromToken` internally use `Token::fromString` to
- `Item::fromEncodedByteSequence` internally use `ByteSequence::fromEncoded`
- `Item::fromDecodedByteSequence` internally use `ByteSequence::fromDecoded`

Those listed named constructors expect a string or a stringable object as its first argument and the
same argument definition as in `Item::from` for parameters argument.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;

$item = Item::fromPair([
    "hello world", [
        ["a", ByteSequence::fromDecoded("Hello World")],
    ]
]);
$item->value(); // returns "hello world"
$item->type();  // returns Type::String
$item->parameters()["a"]->type();  // returns Type::ByteSequence
$item->parameters()["a"]->value(); // returns StructuredFields\ByteSequence::fromDecoded('Hello World');
echo $item->toHttpValue();         // returns "hello world";a=:SGVsbG8gV29ybGQ=:
```

`Item::fromPair` is an alternative to the `Item::from` named constructor, it expects
a tuple composed by an array as a list where:

- The first member on index `0` represents one of the six (6) item type value;
- The second optional member, on index `1`, **MUST** be an iterable construct containing
  tuples of key-value pairs;

Once instantiated, accessing `Item` properties is done via:

- the method `Item::value` which returns the instance underlying value fully decoded;
- the method `Item::parameters` which returns the parameters associated to the `Item` as a distinct `Parameters` object

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\Item;

$item = Item::from(ByteSequence::fromEncoded("SGVsbG8gV29ybGQ="));
$item->type();       // returns Type::ByteSequence
echo $item->value(); // returns StructuredFields\ByteSequence::fromEncoded("SGVsbG8gV29ybGQ=");
```

**Of note: to instantiate a decimal number type a float MUST be used as the first argument of `Item::from`.**

```php
use Bakame\Http\StructuredFields\Item;

$decimal = Item::from(42.0);
$decimal->type(); //Type::Decimal

$integer = Item::fromPair([42]);
$integer->type(); //return Type::Integer
```
