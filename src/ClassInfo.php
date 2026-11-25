<?php

declare(strict_types=1);

namespace Componenta\Tokenizer;

use ReflectionClass;
use ReflectionEnum;

final class ClassInfo
{
    private null|ReflectionClass|ReflectionEnum $cachedReflector = null;
    private ?string $cachedName = null;
    private ?string $cachedNamespace = null;

    /**
     * Lazy-loaded reflector. May trigger autoloading.
     *
     * @throws \ReflectionException If the declaration cannot be resolved
     */
    public ReflectionClass|ReflectionEnum $reflector {
        get {
            return $this->cachedReflector ??= $this->createReflector();
        }
    }

    public string $name {
        get {
            if ($this->cachedName !== null) {
                return $this->cachedName;
            }

            $pos = strrpos($this->fullyQualifiedName, '\\');

            return $this->cachedName = $pos === false
                ? $this->fullyQualifiedName
                : substr($this->fullyQualifiedName, $pos + 1);
        }
    }

    public string $namespace {
        get {
            if ($this->cachedNamespace !== null) {
                return $this->cachedNamespace;
            }

            $pos = strrpos($this->fullyQualifiedName, '\\');

            return $this->cachedNamespace = $pos === false
                ? ''
                : substr($this->fullyQualifiedName, 0, $pos);
        }
    }

    public bool $isClass {
        get => $this->type === DeclarationType::Class_;
    }

    public bool $isInterface {
        get => $this->type === DeclarationType::Interface_;
    }

    public bool $isTrait {
        get => $this->type === DeclarationType::Trait_;
    }

    public bool $isEnum {
        get => $this->type === DeclarationType::Enum_;
    }

    public bool $isConcrete {
        get => $this->type === DeclarationType::Class_ && !$this->isAbstract;
    }

    public function __construct(
        public readonly string $fullyQualifiedName,
        public readonly DeclarationType $type = DeclarationType::Class_,
        public readonly bool $isAbstract = false,
        public readonly bool $isFinal = false,
        public readonly bool $isReadonly = false,
    ) {}

    /**
     * Checks if the declaration is loaded in memory without triggering autoloading.
     */
    public function exists(): bool
    {
        return match ($this->type) {
            DeclarationType::Interface_ => interface_exists($this->fullyQualifiedName, false),
            DeclarationType::Trait_ => trait_exists($this->fullyQualifiedName, false),
            DeclarationType::Enum_ => enum_exists($this->fullyQualifiedName, false),
            DeclarationType::Class_ => class_exists($this->fullyQualifiedName, false),
        };
    }

    /**
     * @throws \ReflectionException If the declaration cannot be resolved
     */
    private function createReflector(): ReflectionClass|ReflectionEnum
    {
        return match ($this->type) {
            DeclarationType::Enum_ => new ReflectionEnum($this->fullyQualifiedName),
            default => new ReflectionClass($this->fullyQualifiedName),
        };
    }
}
