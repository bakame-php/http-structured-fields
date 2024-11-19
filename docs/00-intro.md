---
title: Introduction
order: 1
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
$container[1]->parameterByName(name: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
```

## Motivation

While they are plenty of HTTP headers and trailers, they have historically come each with their own set of 
rules and constraints when it came to parsing and serializing them. Trying to use the parsing logic of a cookie header
to parse an accept header will fail. The various parsing logics hinders HTTP headers and trailers usage, modernization
or security. The [Structured Field RFC](https://www.rfc-editor.org/rfc/rfc9651.html) aim at tackling those issues by
unifying HTTP headers and trailers parsing and serializing.

New HTTP headers or trailers (called HTTP fields) are encouraged to use the RFC algorithm, data and value types and
ongoing discussions are happening to [retrofit existing headers that do not match the RFC](https://httpwg.org/http-extensions/draft-ietf-httpbis-retrofit.html) into new 
shapes that would be compatible with it.

## Foreword

> [!CAUTION]
> While this package parses and serializes HTTP field value, it does not validate its content
> against any conformance rule out of the box. You are still required to perform such a
> compliance check against the constraints of the corresponding field. While Content
> validation is still possible and higly encouraged when using this library. Because
> of the wide variety of HTTP fields it can not be made mandatory.

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

## Using the package

- [Basic usage](01-basic-usage.md)
- [Parsing and Serializing](02-parsing-serializing.md)
- [Accessing The Field Values](03-field-values.md)
- [Working with the Containers Data Type](04-containers.md)
- [Structured Field Validation](05-validation.md)
- [Interacting with the PHP Ecosystem](07-extensions.md)
