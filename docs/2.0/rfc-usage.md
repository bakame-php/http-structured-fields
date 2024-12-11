---
layout: default
title: RFC Usage
---

# RFC Usage

The IETF RFC defines two algorithms regarding HTTP headers and trailers, one to parse them and
another one to serialize them.

## Parsing a field

The package enabled HTTP header or HTTP trailer parsing. We will refer to them as HTTP fields
for the rest of the documentation as it is how they are named in the IETF RFC.

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

The `$permission` variable is a `Dictionary` container instance. you can now access each permission individually
using the container public API:

```php
$permissions['picture-in-picture']->isEmpty(); // returns true because the list is empty
count($permissions['geolocation']);            // returns 2 the 'geolocation' feature has 2 values associated to it via a list
$permissions['geolocation'][-1]->value();      // returns the last value of the list 'https://example.com/'
$permissions['camera']->value()->toString();   // returns '*' the sole value attached to the 'camera' feature
isset($permissions['yolo']);                   // returns false this permission does not exust
$permissions->isEmpty();                       // returns false the dictionary contains some permissions
echo $permissions;                             // returns 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'
```

<p class="message-warning">If parsing fails a <code>SyntaxError</code> exception is thrown with the information about why it failed.</p>

## Serializing a field

Conversely, if you need to quickly create and serialize a permission policy HTTP field text representation, the package
provides a serializer mechanism which strictly follow the RFC:

```php
echo DataType::Dictionary->serialize([
    ['picture-in-picture', []],
    ['geolocation', [[Token::fromString('self'), "https://example.com"]]],
    ['camera', Token::fromString('*')],
]);
// returns picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*
```

Again, we start from the knowledge that the field is a `Dictionary`. The content is added
using lists or ordered map represented as collection of pairs to respect value position.
As such we can turn the iterable construct we have into a proper HTTP field text representation
by applying the serialization mechanism described in the RFC.

Because field building may look overwhelming, at first, the package exposes numerous methods to
simplify this process for you. But nevertheless it is important to understand that even without
dwelling too much into the ins and out of the package you can easily and quickly parse or
serialize compliant fields in PHP base solely on the RFC information.
