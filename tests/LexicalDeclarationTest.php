<?php

declare(strict_types=1);

use Componenta\Tokenizer\Tokenizer;
use Componenta\Tokenizer\TokenizerInterface;

it('preserves namespace and discovers declarations inside captured closures', function (string $source, array $expected): void {
    $declarations = (new Tokenizer())->parse($source, TokenizerInterface::SEARCH_CLASSES);

    expect(array_map(static fn ($info) => $info->fullyQualifiedName, $declarations))->toBe($expected);
})->with([
    'namespace comment' => ['<?php namespace /* comment */ Demo; class A {}', ['Demo\\A']],
    'braced namespace comment' => ['<?php namespace Demo /* comment */ { class A {} } namespace /* comment */ { class B {} }', ['Demo\\A', 'B']],
    'closure capture' => ['<?php namespace Demo; $x = 1; $fn = function () use ($x) { class Captured {} }; class After {}', ['Demo\\Captured', 'Demo\\After']],
    'comment before capture' => ['<?php $x = 1; $fn = function () use /* capture */ ($x) { class Captured {} };', ['Captured']],
    'contextual method names' => ['<?php namespace Demo; class A { public function namespace() {} public function use() {} public function class() {} } class B {}', ['Demo\\A', 'Demo\\B']],
    'static member name' => ['<?php namespace Demo; class A { public const namespace = 1; } A::namespace; class B {}', ['Demo\\A', 'Demo\\B']],
    'grouped import and trait adaptation' => ['<?php namespace Demo; use Other\\{A, B}; trait T { public function f() {} } class A { use T { f as alias; } } class B {}', ['Demo\\A', 'Demo\\B']],
    'anonymous class with closure argument' => ['<?php namespace Demo; $a = new class(function () { class ArgumentClass {} }) {}; class B {}', ['Demo\\ArgumentClass', 'Demo\\B']],
]);
