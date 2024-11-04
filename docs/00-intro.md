# Introduction

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize 
create, update and validate HTTP Structured Fields in PHP according to the [RFC9651](https://www.rfc-editor.org/rfc/rfc9651.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\OuterList;

//1 - parsing an Accept Header
$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$field = OuterList::fromRfc9651($fieldValue);
$field[1]->value()->toString(); // returns 'application/xhtml+xml'
$field[1]->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```
`
## Using the package

Head on over to the next chapter for a short demonstration.

[Basic usage](/docs/01-basic-usage.md) →`
