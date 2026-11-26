<?php

declare(strict_types=1);

use Componenta\Tokenizer\Tokenizer;
use Componenta\Tokenizer\TokenizerInterface;

it('treats a closing PHP tag as a statement terminator for namespaces and imports', function (string $source, array $expected): void {
    token_get_all($source, TOKEN_PARSE);

    $tokenizer = new Tokenizer();
    $found = $tokenizer->parse($source);
    $names = array_map(static fn ($info) => $info->fullyQualifiedName, $found);

    expect($names)->toBe($expected)
        ->and(array_map(
            static fn ($info) => $info->fullyQualifiedName,
            $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES),
        ))->toBe($expected);
})->with([
    'namespace terminated by tag' => [
        '<?php namespace TagNamespace ?><?php class After {}',
        ['TagNamespace\\After'],
    ],
    'namespace with comment and inline HTML' => [
        '<?php namespace TagNamespace /* comment */ ?>HTML<?php final class After {}',
        ['TagNamespace\\After'],
    ],
    'import terminated by tag' => [
        '<?php namespace TagNamespace; use Vendor\\Service ?><?php class After {}',
        ['TagNamespace\\After'],
    ],
    'aliased import with inline HTML' => [
        '<?php namespace TagNamespace; use Vendor\\Service as Alias ?>HTML<?php class After {} class Next {}',
        ['TagNamespace\\After', 'TagNamespace\\Next'],
    ],
    'function and constant imports' => [
        '<?php namespace TagNamespace; use function Vendor\\run ?><?php use const Vendor\\FLAG ?><?php class After {}',
        ['TagNamespace\\After'],
    ],
    'grouped import' => [
        '<?php namespace TagNamespace; use Vendor\\{Service, Other} ?><?php class After {}',
        ['TagNamespace\\After'],
    ],
    'trait use terminated by tag' => [
        '<?php namespace TagNamespace; class First { use ImportedTrait ?><?php } class After {}',
        ['TagNamespace\\First', 'TagNamespace\\After'],
    ],
    'keyword namespace' => [
        '<?php namespace readonly ?><?php class After {}',
        ['readonly\\After'],
    ],
]);

it('retains modifiers after an import followed by a new PHP block', function (): void {
    $source = '<?php namespace TagNamespace; use Vendor\\Service ?><?php final readonly class After {}';
    token_get_all($source, TOKEN_PARSE);

    $result = new Tokenizer()->parse($source);

    expect($result)->toHaveCount(1)
        ->and($result[0]->fullyQualifiedName)->toBe('TagNamespace\\After')
        ->and($result[0]->isFinal)->toBeTrue()
        ->and($result[0]->isReadonly)->toBeTrue();
});
