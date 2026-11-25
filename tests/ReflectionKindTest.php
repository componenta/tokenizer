<?php

declare(strict_types=1);

use Componenta\Tokenizer\ClassInfo;
use Componenta\Tokenizer\DeclarationType;

class KindRegressionClass {}
interface KindRegressionInterface {}
trait KindRegressionTrait {}
enum KindRegressionEnum { case Value; }

it('resolves reflection only for the matching declaration kind', function (string $name, DeclarationType $actual, DeclarationType $requested): void {
    $info = new ClassInfo($name, $requested);

    if ($actual === $requested) {
        expect($info->exists())->toBeTrue()
            ->and($info->reflector->getName())->toBe($name);
        return;
    }

    expect($info->exists())->toBeFalse()
        ->and(fn () => $info->reflector)->toThrow(ReflectionException::class);
})->with([
    [KindRegressionClass::class, DeclarationType::Class_],
    [KindRegressionInterface::class, DeclarationType::Interface_],
    [KindRegressionTrait::class, DeclarationType::Trait_],
    [KindRegressionEnum::class, DeclarationType::Enum_],
])->with(DeclarationType::cases());

it('resolves aliases of the correct kind without requiring identical spelling', function (): void {
    $alias = 'KindRegressionClassAlias';
    class_alias(KindRegressionClass::class, $alias);

    $info = new ClassInfo(strtolower($alias));
    expect($info->exists())->toBeTrue()
        ->and($info->reflector->getName())->toBe(KindRegressionClass::class);
});
