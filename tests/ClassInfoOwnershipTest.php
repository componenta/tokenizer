<?php

declare(strict_types=1);

use Componenta\Tokenizer\ClassInfo;
use Componenta\Tokenizer\DeclarationType;

final class MetadataOriginalFixture {}
final class MetadataUnrelatedFixture {}
enum MetadataEnumFixture { case Value; }
interface MetadataInterfaceFixture {}

it('does not allow a returned reflector to replace the reflected declaration', function (): void {
    $info = new ClassInfo(MetadataOriginalFixture::class, isFinal: true);

    $reflector = $info->reflector;
    $reflector->__construct(MetadataUnrelatedFixture::class);

    expect($reflector->getName())->toBe(MetadataUnrelatedFixture::class)
        ->and($info->reflector->getName())->toBe(MetadataOriginalFixture::class)
        ->and($info->fullyQualifiedName)->toBe(MetadataOriginalFixture::class);
});

it('checks the declared kind when determining if metadata exists', function (): void {
    expect(new ClassInfo(MetadataOriginalFixture::class)->exists())->toBeTrue()
        ->and(new ClassInfo(MetadataEnumFixture::class, DeclarationType::Enum_)->exists())->toBeTrue()
        ->and(new ClassInfo(MetadataEnumFixture::class, DeclarationType::Class_)->exists())->toBeFalse()
        ->and(new ClassInfo(MetadataInterfaceFixture::class, DeclarationType::Class_)->exists())->toBeFalse()
        ->and(new ClassInfo(MetadataInterfaceFixture::class, DeclarationType::Interface_)->exists())->toBeTrue();
});
