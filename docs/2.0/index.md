---
layout: default
title: Installation
---

# Installation

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields:^2.0
```

## Foreword

<p class="message-warning">
While this package parses and serializes HTTP field value, it does not validate its content
against any conformance rule out of the box. You are still required to perform such a
compliance check against the constraints of the corresponding field. While Content
validation is still possible and highly encouraged when using this library. Because
of the wide variety of HTTP fields it can not be made mandatory.
</p>
