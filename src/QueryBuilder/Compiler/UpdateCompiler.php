<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Compiler;

final readonly class UpdateCompiler implements SqlCompilerInterface
{
    /**
     * @param array<string, string> $set   column => placeholder
     * @param list<string>          $where
     */
    public function __construct(
        private ?string $table,
        private array   $set,
        private array   $where,
    ) {
    }

    public function compile(): string
    {
        if ($this->table === null || $this->set === []) {
            throw new \LogicException('UPDATE requires table and set values.');
        }

        $setParts = [];
        foreach ($this->set as $column => $placeholder) {
            $setParts[] = $column . ' = ' . $placeholder;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $setParts);

        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return $sql;
    }
}
