<?php

declare(strict_types=1);

namespace Componenta\Tokenizer;

enum DeclarationType: string
{
    case Class_ = 'class';
    case Interface_ = 'interface';
    case Trait_ = 'trait';
    case Enum_ = 'enum';
}
