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

        $columns = array_keys($this->values);
        $placeholders = array_values($this->values);

        return 'INSERT INTO ' . $this->table
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
    }
}
