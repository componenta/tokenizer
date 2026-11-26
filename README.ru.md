# Componenta Tokenizer

[English version](README.md)

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
| PHP Reflection | Используется при обращении к `ClassInfo::$reflector`; может инициировать автозагрузку. |

## Что предоставляет пакет

- `TokenizerInterface`: интерфейс разбора PHP-кода.
- `Tokenizer`: реализация на основе `token_get_all()`.
- `ClassInfo`: неизменяемое описание найденного объявления.
- `DeclarationType`: enum для class, interface, trait и enum.

Лексический разбор не выполняет исходный код и не инициирует автозагрузку объявлений.
Именованные объявления возвращаются в порядке следования в исходнике, включая
условные объявления, тела функций и статические замыкания в атрибутах PHP 8.5.
Анонимные классы игнорируются. Поддерживаются обе формы пространств имён и
завершение инструкций закрывающим PHP-тегом. Входной код должен быть синтаксически
корректным; поведение на некорректном коде не определено.

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

Ограничивайте типы объявлений с помощью битовой маски. Нулевая маска не выбирает ни одного объявления.

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

Свойства `isClass`, `isInterface`, `isTrait` и `isEnum` описывают вид объявления.
`isAbstract`, `isFinal` и `isReadonly` отражают явно указанные модификаторы класса,
а не неявные свойства интерфейсов или перечислений во время выполнения.
`isConcrete` означает неабстрактный класс, но не гарантирует доступность конструктора.
`name` — короткое имя, `namespace` — пустая строка для глобального пространства имён.

`ClassInfo` также можно создать напрямую, передав полное имя, `DeclarationType`
(по умолчанию `Class_`) и три флага модификаторов.

## Рефлексия

`ClassInfo::$reflector` создаётся лениво и может инициировать автозагрузку.
Каждое обращение создаёт независимый объект рефлексии: изменение полученного
объекта не влияет на последующие обращения. `exists()` проверяет наличие уже загруженного объявления без автозагрузки с учётом вида объявления. Несовпадение вида загруженного объявления
с метаданными или отсутствие объявления при обращении к `reflector` вызывает `ReflectionException`.

```php
if ($info->exists()) {
    $reflector = $info->reflector;
}
```

Для чистого анализа исходников не обращайтесь к `reflector`; используйте поля `ClassInfo`.

## Разработка

Выполните `composer install` и `composer test`.
