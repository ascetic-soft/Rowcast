<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

interface SqlCompilerInterface
{
    public function compile(): string;
}
