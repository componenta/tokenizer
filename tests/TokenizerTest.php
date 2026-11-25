<?php

declare(strict_types=1);

use Componenta\Tokenizer\DeclarationType;
use Componenta\Tokenizer\Tokenizer;
use Componenta\Tokenizer\TokenizerInterface;

beforeEach(function () {
    $this->tokenizer = new Tokenizer();
});

// --- Empty / no declarations ---

it('returns empty list for empty source', function () {
    $result = $this->tokenizer->parse('');

    expect($result)->toBe([]);
});

it('returns empty list for source without declarations', function () {
    $result = $this->tokenizer->parse('<?php $x = 1;');

    expect($result)->toBe([]);
});

// --- Declaration types ---

it('detects declaration type', function (string $code, DeclarationType $expectedType) {
    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe($expectedType);
})->with([
    'class' => ['<?php class Foo {}', DeclarationType::Class_],
    'interface' => ['<?php interface Foo {}', DeclarationType::Interface_],
    'trait' => ['<?php trait Foo {}', DeclarationType::Trait_],
    'enum' => ['<?php enum Foo {}', DeclarationType::Enum_],
]);

// --- Namespaces ---

it('finds class in simple namespace', function () {
    $result = $this->tokenizer->parse('<?php namespace App\\Models; class User {}');

    expect($result)->toHaveCount(1)
        ->and($result[0]->fullyQualifiedName)->toBe('App\\Models\\User')
        ->and($result[0]->name)->toBe('User')
        ->and($result[0]->namespace)->toBe('App\\Models');
});

it('finds class in braced namespace', function () {
    $result = $this->tokenizer->parse('<?php namespace App\\Controllers { class HomeController {} }');

    expect($result)->toHaveCount(1)
        ->and($result[0]->namespace)->toBe('App\\Controllers');
});

it('handles global braced namespace', function () {
    $result = $this->tokenizer->parse('<?php namespace { class GlobalClass {} }');

    expect($result)->toHaveCount(1)
        ->and($result[0]->namespace)->toBe('')
        ->and($result[0]->fullyQualifiedName)->toBe('GlobalClass');
});

it('handles multiple braced namespaces', function () {
    $code = <<<'PHP'
    <?php
    namespace First { class A {} }
    namespace Second { class B {} }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->fullyQualifiedName)->toBe('First\\A')
        ->and($result[1]->fullyQualifiedName)->toBe('Second\\B');
});

it('handles deep namespace', function () {
    $ns = 'A\\B\\C\\D\\E\\F\\G\\H\\I\\J';

    $result = $this->tokenizer->parse("<?php namespace {$ns}; class Deep {}");

    expect($result)->toHaveCount(1)
        ->and($result[0]->namespace)->toBe($ns)
        ->and($result[0]->fullyQualifiedName)->toBe("{$ns}\\Deep");
});

it('handles global class after braced namespace', function () {
    $code = <<<'PHP'
    <?php
    namespace App {
        class Namespaced {}
    }
    namespace {
        class Global_ {}
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->fullyQualifiedName)->toBe('App\\Namespaced')
        ->and($result[1]->fullyQualifiedName)->toBe('Global_');
});

// --- Modifiers ---

it('detects class modifiers', function (string $code, bool $isAbstract, bool $isFinal, bool $isReadonly) {
    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->isAbstract)->toBe($isAbstract)
        ->and($result[0]->isFinal)->toBe($isFinal)
        ->and($result[0]->isReadonly)->toBe($isReadonly);
})->with([
    'abstract' => ['<?php abstract class A {}', true, false, false],
    'final' => ['<?php final class A {}', false, true, false],
    'readonly' => ['<?php readonly class A {}', false, false, true],
    'final readonly' => ['<?php final readonly class A {}', false, true, true],
    'abstract readonly' => ['<?php abstract readonly class A {}', true, false, true],
    'no modifiers' => ['<?php class A {}', false, false, false],
]);

it('resets modifiers between declarations', function () {
    $code = <<<'PHP'
    <?php
    abstract class First {}
    class Second {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->isAbstract)->toBeTrue()
        ->and($result[1]->isAbstract)->toBeFalse();
});

it('does not carry modifiers across interface to next class', function () {
    $code = <<<'PHP'
    <?php
    interface First {}
    class Second {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->type)->toBe(DeclarationType::Interface_)
        ->and($result[1]->isAbstract)->toBeFalse()
        ->and($result[1]->isFinal)->toBeFalse();
});

it('does not leak abstract method modifiers into later classes', function () {
    $code = <<<'PHP'
    <?php
    abstract class Owner {
        abstract public function someFunction(): void;
    }
    class NotAbstract {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->isAbstract)->toBeTrue()
        ->and($result[1]->isAbstract)->toBeFalse();
});

it('does not leak readonly from property promotion', function () {
    $code = <<<'PHP'
    <?php
    class A {
        public function __construct(
            readonly int $x
        ) {}
    }
    class B {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->isReadonly)->toBeFalse()
        ->and($result[1]->isReadonly)->toBeFalse();
});

// --- Search modes ---

it('filters declarations by search mode', function (int $mode, int $expectedCount) {
    $code = <<<'PHP'
    <?php
    class C {}
    interface I {}
    trait T {}
    enum E {}
    PHP;

    $result = $this->tokenizer->parse($code, $mode);

    expect($result)->toHaveCount($expectedCount);
})->with([
    'classes only' => [TokenizerInterface::SEARCH_CLASSES, 1],
    'interfaces only' => [TokenizerInterface::SEARCH_INTERFACES, 1],
    'traits only' => [TokenizerInterface::SEARCH_TRAITS, 1],
    'enums only' => [TokenizerInterface::SEARCH_ENUMS, 1],
    'all' => [TokenizerInterface::SEARCH_ALL, 4],
    'classes and interfaces' => [TokenizerInterface::SEARCH_CLASSES | TokenizerInterface::SEARCH_INTERFACES, 2],
    'all except enums' => [TokenizerInterface::SEARCH_ALL & ~TokenizerInterface::SEARCH_ENUMS, 3],
]);

// --- Anonymous classes ---

it('ignores simple anonymous class', function () {
    $code = <<<'PHP'
    <?php
    class Named {}
    $anon = new class {};
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Named');
});

it('ignores nested anonymous classes', function () {
    $code = <<<'PHP'
    <?php
    class Outer {
        public function create() {
            return new class {
                public function inner() {
                    return new class {};
                }
            };
        }
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Outer');
});

it('ignores anonymous class with constructor arguments', function () {
    $code = <<<'PHP'
    <?php
    class Named {
        public function create() {
            return new class(1, 'arg') {
                public function __construct(int $a, string $b) {}
            };
        }
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Named');
});

it('ignores anonymous class with extends and implements', function () {
    $code = <<<'PHP'
    <?php
    class Named {}
    $anon = new class extends Named implements \Countable {
        public function count(): int { return 0; }
    };
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Named');
});

// --- Attributes ---

it('handles attributes on classes', function () {
    $code = <<<'PHP'
    <?php
    #[Attribute]
    #[AnotherAttribute('value')]
    class Decorated {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Decorated');
});

it('handles grouped attributes', function () {
    $code = <<<'PHP'
    <?php
    #[First, Second('x')]
    class MultiAttr {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('MultiAttr');
});

it('handles multiple separate attributes', function () {
    $code = <<<'PHP'
    <?php
    #[First]
    #[Second]
    #[Third('value')]
    class MultipleAttrs {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('MultipleAttrs');
});

it('handles nested brackets in attributes', function () {
    $code = <<<'PHP'
    <?php
    #[Attribute(['key' => ['nested' => 'value']])]
    class WithNestedAttr {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('WithNestedAttr');
});

it('ignores class constant references inside valid attribute arguments', function () {
    $code = <<<'PHP'
    <?php
    #[Attr([SomeClass::class, 'method'])]
    class Decorated {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Decorated');
});

// --- Use statements ---

it('skips namespace use statements', function () {
    $code = <<<'PHP'
    <?php
    namespace App;
    use Some\Other\Namespace\Class_;
    use Another\{ClassA, ClassB};
    class MyClass {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->fullyQualifiedName)->toBe('App\\MyClass');
});

it('skips use inside braced namespace', function () {
    $code = <<<'PHP'
    <?php
    namespace A {
        use B\C;
        class D {}
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->fullyQualifiedName)->toBe('A\\D');
});

it('handles trait use with conflict resolution', function () {
    $code = <<<'PHP'
    <?php
    class MyClass {
        use SomeTrait {
            method1 as private;
            method2 as protected renamedMethod;
        }
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('MyClass');
});

// --- ::class constant access ---

it('ignores ::class constant access', function () {
    $code = <<<'PHP'
    <?php
    Foo::class;
    class Real {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Real');
});

it('ignores self/static/parent ::class inside class body', function () {
    $code = <<<'PHP'
    <?php
    class Base {}
    class A extends Base {
        public function f(): array {
            return [self::class, static::class, parent::class];
        }
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Base')
        ->and($result[1]->name)->toBe('A');
});

// --- Edge cases ---

it('ignores class keywords in strings', function () {
    $code = <<<'PHP'
    <?php
    class Real {}
    $code = "class Fake {}";
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Real');
});

it('ignores class keywords in comments', function () {
    $code = <<<'PHP'
    <?php
    // class InlineComment {}
    /* class BlockComment {} */
    /** class DocBlock {} */
    class Real {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Real');
});

it('handles multiple PHP tags', function () {
    $result = $this->tokenizer->parse('<?php class A {} ?><?php class B {} ?>');

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('A')
        ->and($result[1]->name)->toBe('B');
});

it('finds backed enum with cases', function () {
    $code = <<<'PHP'
    <?php
    enum Status: string {
        case OK = 'ok';
    }
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(1)
        ->and($result[0]->name)->toBe('Status')
        ->and($result[0]->type)->toBe(DeclarationType::Enum_);
});

it('finds class after complex control flow in class body', function () {
    $code = <<<'PHP'
    <?php
    class First {
        public function method(): void {
            if (true) {
                for ($i = 0; $i < 10; $i++) {
                    match($i) {
                        0 => 'zero',
                        default => 'other',
                    };
                }
            }
        }
    }
    class Second {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('First')
        ->and($result[1]->name)->toBe('Second');
});

it('finds enum with methods containing control flow', function () {
    $code = <<<'PHP'
    <?php
    enum Color: string {
        case Red = 'red';
        case Blue = 'blue';

        public function label(): string {
            return match($this) {
                self::Red => 'Red Color',
                self::Blue => 'Blue Color',
            };
        }
    }
    class After {}
    PHP;

    $result = $this->tokenizer->parse($code);

    expect($result)->toHaveCount(2)
        ->and($result[0]->name)->toBe('Color')
        ->and($result[0]->type)->toBe(DeclarationType::Enum_)
        ->and($result[1]->name)->toBe('After');
});
