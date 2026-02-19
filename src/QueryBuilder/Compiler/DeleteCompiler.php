<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final readonly class DeleteCompiler implements SqlCompilerInterface
{
    /**
     * @param list<string> $where
     */
    public function __construct(
        private ?string $table,
        private array   $where,
    ) {
    }

    public function compile(): string
    {
        if ($this->table === null) {
            throw new \LogicException('DELETE requires table.');
        }

        $sql = 'DELETE FROM ' . $this->table;

        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }
}
