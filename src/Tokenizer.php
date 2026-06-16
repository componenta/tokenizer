<?php

declare(strict_types=1);

namespace Componenta\Tokenizer;

final readonly class Tokenizer implements TokenizerInterface
{
    /** @var array<int, array{flag: int, type: DeclarationType, modifiers: bool}> */
    private const array DECLARATION_MAP = [
        T_CLASS => [
            'flag' => self::SEARCH_CLASSES,
            'type' => DeclarationType::Class_,
            'modifiers' => true,
        ],
        T_INTERFACE => [
            'flag' => self::SEARCH_INTERFACES,
            'type' => DeclarationType::Interface_,
            'modifiers' => false,
        ],
        T_TRAIT => [
            'flag' => self::SEARCH_TRAITS,
            'type' => DeclarationType::Trait_,
            'modifiers' => false,
        ],
        T_ENUM => [
            'flag' => self::SEARCH_ENUMS,
            'type' => DeclarationType::Enum_,
            'modifiers' => false,
        ],
    ];

    public function parse(string $source, int $mode = self::SEARCH_ALL): array
    {
        $tokens = token_get_all($source);
        $tokenCount = count($tokens);
        $declarations = [];

        $currentNamespace = '';
        $isAbstract = false;
        $isFinal = false;
        $isReadonly = false;

        // Only declaration-opening braces increment classDepth; all closing
        // braces decrement it. This is sufficient for valid PHP since named
        // declarations cannot nest inside class bodies.
        $classDepth = 0;
        $pendingClassBlock = false;

        $afterDoubleColon = false;

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                if ($pendingClassBlock) {
                    $classDepth++;
                    $pendingClassBlock = false;
                }

                continue;
            }

            if ($token === '}') {
                if ($classDepth > 0) {
                    $classDepth--;
                }

                continue;
            }

            if ($token === ';') {
                $pendingClassBlock = false;

                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            $tokenId = $token[0];

            if ($tokenId === T_WHITESPACE || $tokenId === T_COMMENT || $tokenId === T_DOC_COMMENT) {
                continue;
            }

            if ($tokenId === T_DOUBLE_COLON) {
                $afterDoubleColon = true;

                continue;
            }

            if ($tokenId === T_ATTRIBUTE) {
                $i = $this->skipAttribute($tokens, $i, $tokenCount);

                continue;
            }

            if ($tokenId === T_NAMESPACE) {
                $namespaceResult = $this->parseNamespace($tokens, $i, $tokenCount);
                $currentNamespace = $namespaceResult['namespace'];
                $i = $namespaceResult['nextIndex'] - 1;
                $isAbstract = $isFinal = $isReadonly = $afterDoubleColon = false;

                continue;
            }

            // Skip top-level use imports (not trait use inside class bodies)
            if ($tokenId === T_USE && $classDepth === 0) {
                $i = $this->skipUseStatement($tokens, $i, $tokenCount);
                $isAbstract = $isFinal = $isReadonly = $afterDoubleColon = false;

                continue;
            }

            if ($tokenId === T_ABSTRACT) {
                $isAbstract = true;
                $afterDoubleColon = false;

                continue;
            }

            if ($tokenId === T_FINAL) {
                $isFinal = true;
                $afterDoubleColon = false;

                continue;
            }

            if ($tokenId === T_READONLY) {
                $isReadonly = true;
                $afterDoubleColon = false;

                continue;
            }

            if (isset(self::DECLARATION_MAP[$tokenId])) {
                // Skip ::class constant access
                if ($afterDoubleColon) {
                    $isAbstract = $isFinal = $isReadonly = $afterDoubleColon = false;

                    continue;
                }

                $pendingClassBlock = true;

                if ($classDepth === 0) {
                    [
                        'flag' => $searchFlag,
                        'type' => $type,
                        'modifiers' => $supportsModifiers,
                    ] = self::DECLARATION_MAP[$tokenId];

                    if (($mode & $searchFlag) !== 0) {
                        $result = $this->parseDeclaration(
                            $tokens,
                            $i,
                            $tokenCount,
                            $currentNamespace,
                            $type,
                            $supportsModifiers ? $isAbstract : false,
                            $supportsModifiers ? $isFinal : false,
                            $supportsModifiers ? $isReadonly : false,
                        );

                        if ($result !== null) {
                            $declarations[] = $result['declaration'];
                            $i = $result['nextIndex'] - 1;
                        }
                    }
                }

                $isAbstract = $isFinal = $isReadonly = false;

                continue;
            }

            $isAbstract = $isFinal = $isReadonly = $afterDoubleColon = false;
        }

        return $declarations;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function skipAttribute(array $tokens, int $index, int $tokenCount): int
    {
        $depth = 1;
        $index++;

        while ($index < $tokenCount && $depth > 0) {
            $token = $tokens[$index];

            if ($token === '[') {
                $depth++;
            } elseif ($token === ']') {
                $depth--;
            }

            $index++;
        }

        return $index - 1;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{namespace: string, nextIndex: int}
     */
    private function parseNamespace(array $tokens, int $index, int $tokenCount): array
    {
        $index++;
        $namespace = '';

        while ($index < $tokenCount) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                $index++;

                continue;
            }

            $tokenId = $token[0];

            if ($tokenId === T_STRING
                || $tokenId === T_NS_SEPARATOR
                || $tokenId === T_NAME_QUALIFIED
                || $tokenId === T_NAME_FULLY_QUALIFIED
            ) {
                $namespace .= $token[1];
            } elseif ($tokenId !== T_WHITESPACE) {
                break;
            }

            $index++;
        }

        return [
            'namespace' => ltrim($namespace, '\\'),
            'nextIndex' => $index,
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function skipUseStatement(array $tokens, int $index, int $tokenCount): int
    {
        $braceDepth = 0;

        while ($index < $tokenCount) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === '{') {
                    $braceDepth++;
                } elseif ($token === '}') {
                    $braceDepth--;

                    if ($braceDepth === 0) {
                        return $index;
                    }
                } elseif ($token === ';' && $braceDepth === 0) {
                    return $index;
                }
            }

            $index++;
        }

        return $index - 1;
    }

    /**
     * Returns null for anonymous classes (no T_STRING after keyword).
     * Named declarations always use bare identifiers (T_STRING),
     * never T_NAME_QUALIFIED or T_NAME_FULLY_QUALIFIED.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     * @return array{declaration: ClassInfo, nextIndex: int}|null
     */
    private function parseDeclaration(
        array $tokens,
        int $index,
        int $tokenCount,
        string $namespace,
        DeclarationType $type,
        bool $isAbstract,
        bool $isFinal,
        bool $isReadonly,
    ): ?array {
        $index++;

        while ($index < $tokenCount) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                return null;
            }

            $tokenId = $token[0];

            if ($tokenId === T_WHITESPACE || $tokenId === T_COMMENT || $tokenId === T_DOC_COMMENT) {
                $index++;

                continue;
            }

            if ($tokenId === T_STRING) {
                $name = $token[1];
                $fullyQualifiedName = $namespace !== '' ? $namespace . '\\' . $name : $name;

                return [
                    'declaration' => new ClassInfo(
                        $fullyQualifiedName,
                        $type,
                        $isAbstract,
                        $isFinal,
                        $isReadonly,
                    ),
                    'nextIndex' => $index + 1,
                ];
            }

            return null;
        }

        return null;
    }
}
