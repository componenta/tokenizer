<?php

declare(strict_types=1);

namespace Componenta\Tokenizer;

/**
 * Finds class, interface, trait, and enum declarations in PHP source code.
 *
 * Named declarations inside function bodies (including PHP 8.5 attribute
 * callbacks) are discovered without executing them. Anonymous classes are ignored.
 * Both simple (`namespace Foo;`) and
 * bracketed (`namespace Foo { }`) namespace syntax are supported.
 *
 * Implementations assume syntactically valid PHP input.
 * Behavior on malformed source code is undefined.
 */
interface TokenizerInterface
{
    public const int SEARCH_CLASSES = 1;
    public const int SEARCH_INTERFACES = 2;
    public const int SEARCH_TRAITS = 4;
    public const int SEARCH_ENUMS = 8;
    public const int SEARCH_ALL = self::SEARCH_CLASSES
    | self::SEARCH_INTERFACES
    | self::SEARCH_TRAITS
    | self::SEARCH_ENUMS;

    /**
     * @param string $source PHP source code to parse
     * @param int-mask-of<self::SEARCH_*> $mode Bitwise combination of SEARCH_* constants
     * @return list<ClassInfo>
     */
    public function parse(string $source, int $mode = self::SEARCH_ALL): array;
}
