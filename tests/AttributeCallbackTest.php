<?php

declare(strict_types=1);

use Componenta\Tokenizer\Tokenizer;
use Componenta\Tokenizer\TokenizerInterface;

it('finds named declarations in PHP 8.5 attribute callbacks without invoking them', function (): void {
    $source = <<<'PHP'
    <?php
    namespace TokenizerAttributeCallbackRegression;

    #[Callback(static function (): void {
        class Inner {}
        interface InnerContract {}
    })]
    function decorated(): void {}

    class After {}
    PHP;

    // Validate that the fixture compiles on this runtime without calling the callback.
    eval(substr($source, 5));

    $tokenizer = new Tokenizer();
    $classes = $tokenizer->parse($source);
    expect(array_map(static fn ($info) => $info->fullyQualifiedName, $classes))->toBe([
        'TokenizerAttributeCallbackRegression\\Inner',
        'TokenizerAttributeCallbackRegression\\InnerContract',
        'TokenizerAttributeCallbackRegression\\After',
    ])->and(array_map(
        static fn ($info) => $info->fullyQualifiedName,
        $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES),
    ))->toBe([
        'TokenizerAttributeCallbackRegression\\Inner',
        'TokenizerAttributeCallbackRegression\\After',
    ])->and(class_exists('TokenizerAttributeCallbackRegression\\Inner', false))->toBeFalse();
})->skip(PHP_VERSION_ID < 80500, 'Static closures in attribute arguments require PHP 8.5.');
