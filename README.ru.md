# Componenta Tokenizer

Токенайзер PHP-объявлений. Пакет читает PHP-код как текст и возвращает информацию об именованных классах, интерфейсах, трейтах и enum без обязательной загрузки этих классов в PHP.

Используйте его, когда нужно быстро понять, какие объявления есть в файле: например, для обнаружения атрибутов на уровне приложения, построения карт обработчиков или подготовки кеша.

## Установка

```bash
composer require componenta/tokenizer
```

## Требования

- PHP 8.4+

## Связанные пакеты

| Пакет | Зачем нужен здесь |
|---|---|
| `componenta/class-finder` | Обходит директории с PHP-файлами и применяет фильтры. Внутри использует `componenta/tokenizer` для разбора каждого файла. |
| `componenta/app` и `*-app` пакеты | Используют обнаружение классов во время сборки кеша приложения. |
| PHP Reflection | Используется только когда вызывается `ClassInfo::$reflector` и класс уже доступен через autoload. |

## Что предоставляет пакет

- `TokenizerInterface`: интерфейс разбора PHP-кода.
- `Tokenizer`: реализация на основе `token_get_all()`.
- `ClassInfo`: иммутабельное описание найденного объявления.
- `DeclarationType`: enum для class, interface, trait и enum.

Анонимные классы игнорируются. Входной PHP-код должен быть синтаксически валидным.

## Разбор PHP-кода

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

Каждый элемент результата — `ClassInfo`:

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

## Режимы поиска

Ограничивайте типы объявлений через bitmask:

```php
use Componenta\Tokenizer\TokenizerInterface;

$classes = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);
$contracts = $tokenizer->parse(
    $source,
    TokenizerInterface::SEARCH_INTERFACES | TokenizerInterface::SEARCH_TRAITS,
);
```

Доступные флаги:

- `SEARCH_CLASSES`
- `SEARCH_INTERFACES`
- `SEARCH_TRAITS`
- `SEARCH_ENUMS`
- `SEARCH_ALL`

## Рефлексия

`ClassInfo::$reflector` создаётся лениво и может инициировать автозагрузку. `exists()` проверяет наличие уже загруженного объявления без автозагрузки.

```php
if ($info->exists()) {
    $reflector = $info->reflector;
}
```

Для чистого анализа исходников не обращайтесь к `reflector`; используйте поля `ClassInfo`.

## Разработка

Выполните `composer install` и `composer test`.
