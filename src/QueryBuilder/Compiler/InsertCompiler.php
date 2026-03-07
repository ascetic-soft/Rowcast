<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final readonly class InsertCompiler implements SqlCompilerInterface
{
    /**
     * @param array<string, string> $values column => placeholder
     */
    public function __construct(
        private ?string $table,
        private array   $values,
    ) {
    }

    public function compile(): string
    {
        if ($this->table === null || $this->values === []) {
            throw new \LogicException('INSERT requires table and values.');
        }

        return SqlFragments::buildInsertSql($this->table, $this->values);
    }
}
