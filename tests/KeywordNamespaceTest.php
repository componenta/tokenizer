<?php

declare(strict_types=1);

use Componenta\Tokenizer\Tokenizer;
use Componenta\Tokenizer\TokenizerInterface;

it('discovers declarations in valid single-segment keyword namespaces', function (string $namespace): void {
    $source = '<?php namespace ' . $namespace . '; class Example {} interface Contract {}';

    token_get_all($source, TOKEN_PARSE);

    $tokenizer = new Tokenizer();
    expect(array_map(static fn ($info) => $info->fullyQualifiedName, $tokenizer->parse($source)))->toBe([
        $namespace . '\\Example',
        $namespace . '\\Contract',
    ])->and(array_map(
        static fn ($info) => $info->fullyQualifiedName,
        $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES),
    ))->toBe([$namespace . '\\Example']);
})->with(['readonly', 'class', 'namespace', 'match', 'interface', 'final', '__CLASS__', 'enum']);

it('preserves bracketed keyword namespaces and contextual member names', function (): void {
    $source = <<<'PHP'
    <?php
    namespace readonly /* comment */ {
        class First {
            public function namespace(): void {}
        }
        class Second {}
    }
    namespace class {
        trait Behavior {}
    }
    namespace {
        class GlobalClass {}
    }
    PHP;
    token_get_all($source, TOKEN_PARSE);

    expect(array_map(static fn ($info) => $info->fullyQualifiedName, new Tokenizer()->parse($source)))->toBe([
        'readonly\\First',
        'readonly\\Second',
        'class\\Behavior',
        'GlobalClass',
    ]);
});
