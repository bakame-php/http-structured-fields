# Basic Usage

## Parsing the Field

The first way to use the package is to enable header or trailer parsing. We will refer to them as fields
for the rest of the documentation as it is how they are referred to in the IETF RFC.

Let's say we want to parse the [Permissions-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy#syntax) field. The first thing to know
is that each Structured field is defined against one specific data type which is
available in the package.

For instance, the `Permission-Policy` field is defined as a `Dictionary` as such
we can easily parse it using the package as follows:

```php
use Bakame\Http\StructuredFields\DataType;

$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
//the raw header line is a structured field dictionary
$permissions = DataType::Dictionary->parse($headerLine); // parse the field
```

You can now access each permission individually as follows:

```php
$permissions['picture-in-picture']->isEmpty(); // returns true because the list is empty
count($permissions['geolocation']);            // returns 2 the 'geolocation' feature has 2 values associated to it via a list
$permissions['geolocation'][-1]->value();      // returns the last value of the list 'https://example.com/'
$permissions['camera']->value();               // returns '*' the sole value attached to the 'camera' feature
```

> [!WARNING]
> If parsing fails a `SyntaxError` exception is thrown with the information about why the conversion
> could not be achieved.

## Building the Field

Conversely, if you need to quickly create a permission policy field text representation, the package
provides ways to do so:

```php
echo DataType::Dictionary->serialize([
    ['picture-in-picture', []],
    ['geolocation', [[Token::fromString('self'), "https://example.com"]]],
    ['camera', Token::fromString('*')],
]);
// returns picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*
```

While field building may look overwhelming you will find alternate ways to build the field while reading
the documentation that may suite your business logic better. The goal of the example is to show that even
without dwelling too much into the ins and out of the package you can easily and quickly access or create
compliant fields.

## Structured Fields Values

For a more in depth presentation of each structure and their method please head on over to the next chapter 
to understand the RFC data types and how they relate to PHP.

[Value types](/docs/02-types.md) →
