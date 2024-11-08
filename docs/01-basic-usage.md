# Basic Usage

## Parsing the Field

The first way to use the package is to enable HTTP header or HTTP trailer parsing. We will refer to them
as fields for the rest of the documentation as it is how they are named in the IETF RFC.

Let's say we want to parse the [Permissions-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy#syntax) field. The first thing to know
is that each structured field is defined against one specific data type which is
available in the package.

For instance, the `Permission-Policy` field is defined as a `Dictionary` as such
we can easily parse it using the package as follows:

```php
use Bakame\Http\StructuredFields\DataType;

$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
//the raw header line is a structured field dictionary
$permissions = DataType::Dictionary->parse($headerLine); // parse the field
```

You can now access each permission individually using the following PHP syntax:

```php
$permissions['picture-in-picture']->isEmpty(); // returns true because the list is empty
count($permissions['geolocation']);            // returns 2 the 'geolocation' feature has 2 values associated to it via a list
$permissions['geolocation'][-1]->value();      // returns the last value of the list 'https://example.com/'
$permissions['camera']->value();               // returns '*' the sole value attached to the 'camera' feature
```

> [!WARNING]
> If parsing fails a `SyntaxError` exception is thrown with the information about why the conversion
> could not be achieved.

## Creating a new field

Conversely, if you need to quickly create a permission policy HTTP field text representation, the package
provides the following ways to do so:

```php
echo DataType::Dictionary->serialize([
    ['picture-in-picture', []],
    ['geolocation', [[Token::fromString('self'), "https://example.com"]]],
    ['camera', Token::fromString('*')],
]);
// returns picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*
```

Again, we started from the knowledge that the field is a `Dictionary`, so we will apply the serialization
mechanism described in the RFC to turn the iterable construct we have into a proper HTTP field text 
representation. While field building may look overwhelming, it follows a described and tested process
that the package can easily simplify for you as you will learn while reading this documentation.
The goal of the example is to show that even without dwelling too much into the ins and out
of the package you can easily and quickly parse or serialize compliant fields in PHP.

&larr; [Intro](00-intro.md)  |  [Parsing and Serializing](02-parsing-serializing.md) &rarr;
