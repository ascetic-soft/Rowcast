<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectInterface;

final readonly class UpsertCompiler implements SqlCompilerInterface
{
    /**
     * @param array<string, string> $values column => placeholder
     * @param list<string> $conflictColumns
     * @param list<string> $updateColumns
     */
    public function __construct(
        private ?string $table,
        private array $values,
        private array $conflictColumns,
        private array $updateColumns,
        private DialectInterface $dialect,
    ) {
    }

    public function compile(): string
    {
        if ($this->table === null || $this->values === []) {
            throw new \LogicException('UPSERT requires table and values.');
        }

        $sql = SqlFragments::buildInsertSql($this->table, $this->values);

        return $sql . $this->dialect->compileUpsertClause($this->conflictColumns, $this->updateColumns);
    }
}
