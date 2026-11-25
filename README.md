# Componenta Tokenizer

PHP declaration tokenizer used by class discovery.

## Installation

```bash
composer require componenta/tokenizer
```

## Requirements

- PHP 8.4+

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/class-finder` | Walks PHP files and uses this tokenizer for each source file. |
| `componenta/app` and `*-app` packages | Use class discovery during application cache compilation. |
| PHP Reflection | Used only when `ClassInfo::$reflector` is requested and the declaration is autoloadable. |

## What It Provides

- `TokenizerInterface`: public contract for parsing PHP source.
- `Tokenizer`: token-based parser for named declarations.
- `ClassInfo`: immutable declaration metadata with lazy reflection.
- `DeclarationType`: enum for class, interface, trait, and enum declarations.

Anonymous classes are ignored. The tokenizer assumes syntactically valid PHP input.

## Parsing Source

```php
use Componenta\Tokenizer\Tokenizer;

$declarations = (new Tokenizer())->parse(<<<'PHP'
<?php

namespace App;

final readonly class User {}
interface UserRepository {}
trait Timestamped {}
enum UserStatus: string { case Active = 'active'; }
PHP);
```

Each item is a `ClassInfo`:

```php
$info = $declarations[0];

$info->fullyQualifiedName; // App\User
$info->name;               // User
$info->namespace;          // App
$info->type;               // DeclarationType::Class_
$info->isFinal;            // true
$info->isReadonly;         // true
$info->isConcrete;         // true
```

## Search Modes

Use bitmasks to restrict declaration types:

```php
use Componenta\Tokenizer\TokenizerInterface;

$classes = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);
$contracts = $tokenizer->parse(
    $source,
    TokenizerInterface::SEARCH_INTERFACES | TokenizerInterface::SEARCH_TRAITS,
);
```

Available flags:

- `SEARCH_CLASSES`
- `SEARCH_INTERFACES`
- `SEARCH_TRAITS`
- `SEARCH_ENUMS`
- `SEARCH_ALL`

## Reflection

`ClassInfo::$reflector` is lazy and may trigger autoloading. Each access creates
an independent reflector so callers cannot mutate the reflected identity for later reads. `exists()` checks whether the declaration is already loaded without triggering autoloading and checks the declared kind.

```php
if ($info->exists()) {
    $reflector = $info->reflector;
}
```

## Development

Run `composer install` and `composer test`.
