# Structured Fields Values

## Value type conversion to PHP

The RFC defines several value types for which the package either convert to PHP native type whenever possible
or provides a class based alternative. The table below summarizes the value type system.

| RFC Type      | PHP Type                  | Package Enum Name     | Package Enum Value | RFC min. version |
|---------------|---------------------------|-----------------------|--------------------|------------------|
| Integer       | `int`                     | `Type::Integer`       | `ìnteger`          | RFC8941          |
| Decimal       | `float`                   | `Type::Decimal`       | `decimal`          | RFC8941          |
| String        | `string`                  | `Type::String`        | `string`           | RFC8941          |
| Boolean       | `bool`                    | `Type::Boolean`       | `boolean`          | RFC8941          |
| Token         | class `Token`             | `Type::Token`         | `token`            | RFC8941          |
| Byte Sequence | class `ByteSequence`      | `Type::ByteSequence`  | `binary`           | RFC8941          |
| Date          | class `DateTimeImmutable` | `Type::Date`          | `date`             | RFC9651          |
| DisplayString | class `DisplayString`     | `Type::DisplayString` | `displaystring`    | RFC9651          |

> [!WARNING]
> The translation to PHP native type does not mean that all PHP values are usable. For instance, in the
> following example, what is considered to be a valid string in PHP is not considered as compliant
> to the string type according to the RFC.

```php
$newPermissions = $permissions->add('gyroscope',  ["https://a.bébé.com"]);
 // will trigger a SyntaxError because a structured field string can not contain UTF-8 characters
```

> [!NOTE]
> The `Date` and `DisplayString` types were added in the accepted RFC9651 
> but are not part of the obsolete RFC8941 specification.

The Enum `Type` list all available types and can be used to determine the RFC type
corresponding to a PHP structure using the `Type::fromVariable` static method.
The method will throw if the structure is not recognized. Alternatively
it is possible to use the `Type::tryFromVariable` which will instead
return `null` on unidentified type. On success both methods
return the corresponding enum `Type`.

```php
use Bakame\Http\StructuredFields\Type;

echo Type::fromVariable(42)->value;  // returns 'integer'
echo Type::fromVariable(42.0)->name; // returns 'Decimal'
echo Type::fromVariable(new SplTempFileObject()); // throws InvalidArgument
echo Type::tryFromVariable(new SplTempFileObject()); // returns null
```

To ease validation the `Type::equals`  and `Type::isOneOf` methods are added to check if
the variable is of expected type. It can also be used to compare types.

```php
use Bakame\Http\StructuredFields\Type;

$field = Type::fromVariable('foo');
Type::Date->equals($field);          // returns false
Type::String->equals($field);        // returns true;
Type::Boolean->equals(Type::String); // returns false
Type::fromVariable(42)->isOneOf(Type::Token, Type::Integer); //return true
```

## Custom Value Type

The RFC defines three (3) specific data types that can not be represented by
PHP default type system, for them, we have defined three classes `Token`,
`ByteSequence` and `DisplayString` to help with their representation.

```php
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\DisplayString;
use Bakame\Http\StructuredFields\Token;

Token::fromString(string|Stringable $value): Token
ByteSequence::fromDecoded(string|Stringable $value): ByteSequence;
ByteSequence::fromEncoded(string|Stringable $value): ByteSequence;
DisplayString::fromDecoded(string|Stringable $value): DisplayString;
DisplayString::fromEncoded(string|Stringable $value): DisplayString;
```

All classes are final and immutable; their value can not be modified once
instantiated. To access their value, they expose the following API:

```php
use Bakame\Http\StructuredFields\Token;
use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\DisplayString;

$token = Token::fromString('application/text+xml');
echo $token->toString(); // returns 'application/text+xml'

$byte = DisplayString::fromDecoded('füü');
$byte->decoded(); // returns 'füü'
$byte->encoded(); // returns 'f%c3%bc%c3%bc'

$displayString = ByteSequence::fromDecoded('Hello world!');
$byte->decoded(); // returns 'Hello world!'
$byte->encoded(); // returns 'SGVsbG8gd29ybGQh'

$token->equals($byte); // will return false;
$displayString->equals($byte); // will return false;
$byte->equals(ByteSequence::fromEncoded('SGVsbG8gd29ybGQh')); // will return true

$token->type(); // returns Type::Token enum
$byte->type();  // returns Type::ByteSequence
$displayString->type(); // returns Type::DisplayString
```

> [!WARNING]
> The classes DO NOT expose the `Stringable` interface to help distinguish
> them from the string type or a stringable object

## Structured Field Data Types

The RFC does not provide direct access to those values, to get to these values you need
to use one of its data type structure.

[Value types](/docs/03-data-types.md) →
