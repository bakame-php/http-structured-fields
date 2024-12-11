---
layout: homepage
---

# Introduction

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize, 
create, update and validate HTTP Structured Fields in PHP according to the [RFC9651](https://www.rfc-editor.org/rfc/rfc9651.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\DataType;

$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$container = DataType::List->parse($fieldValue);
$container[1]->value()->toString(); // returns 'application/xhtml+xml'
$container[1]->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
```

# Motivation

While they are plenty of HTTP headers and trailers, they have historically come each with their own set of 
rules and constraints when it came to parsing and serializing them. Trying to use the parsing logic of a cookie header
to parse an accept header will fail. The various parsing logics hinder HTTP headers and trailers usage, modernization
or security. The [Structured Field RFC](https://www.rfc-editor.org/rfc/rfc9651.html) aim at tackling those issues by unifying HTTP headers and trailers
parsing and serializing.

New HTTP headers or trailers (called HTTP fields) are encouraged to use the RFC algorithm, data and value types and
ongoing discussions are happening to [retrofit existing headers that do not match the RFC](https://httpwg.org/http-extensions/draft-ietf-httpbis-retrofit.html) into new 
shapes that would be compatible with it.
