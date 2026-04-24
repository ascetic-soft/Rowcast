<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder;

use AsceticSoft\Rowcast\QueryBuilder\Dialect\DialectInterface;

final readonly class WhereCompiler
{
    /**
     * @param \Closure(string, mixed): string $createParameter
     */
    public function __construct(
        private DialectInterface $dialect,
        private \Closure $createParameter,
    ) {
    }

    /**
     * @param string|array<int|string, mixed> $predicate
     */
    public function compilePredicate(string|array $predicate): ?string
    {
        if (\is_string($predicate)) {
            return $predicate !== '' ? $predicate : null;
        }

        if ($predicate === []) {
            return null;
        }

        $parts = [];
        foreach ($predicate as $key => $value) {
            $stringKey = (string) $key;
            if ($stringKey === '$or') {
                if (!\is_array($value)) {
                    throw new \LogicException('WHERE "$or" expects array of groups.');
                }

                $compiled = $this->compileOrGroup($value);
                if ($compiled !== null) {
                    $parts[] = $compiled;
                }
                continue;
            }

            if ($stringKey === '$and') {
                if (!\is_array($value)) {
                    throw new \LogicException('WHERE "$and" expects array of groups.');
                }

                $compiled = $this->compileAndGroup($value);
                if ($compiled !== null) {
                    $parts[] = $compiled;
                }
                continue;
            }

            $parts[] = $this->compileEntry($stringKey, $value);
        }

        if ($parts === []) {
            return null;
        }

        return implode(' AND ', $parts);
    }

    /**
     * @param array<int|string, mixed> $groups
     */
    public function compileOrGroup(array $groups): ?string
    {
        $compiledGroups = [];
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                throw new \LogicException('WHERE "$or" group must be an array.');
            }

            $compiled = $this->compilePredicate($group);
            if ($compiled !== null && $compiled !== '') {
                $compiledGroups[] = $compiled;
            }
        }

        if ($compiledGroups === []) {
            return null;
        }

        if (\count($compiledGroups) === 1) {
            return $compiledGroups[0];
        }

        $wrapped = array_map(self::wrapGroup(...), $compiledGroups);

        return '(' . implode(' OR ', $wrapped) . ')';
    }

    /**
     * @param array<int|string, mixed> $groups
     */
    public function compileAndGroup(array $groups): ?string
    {
        $compiledGroups = [];
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                throw new \LogicException('WHERE "$and" group must be an array.');
            }

            $compiled = $this->compilePredicate($group);
            if ($compiled !== null && $compiled !== '') {
                $compiledGroups[] = $compiled;
            }
        }

        if ($compiledGroups === []) {
            return null;
        }

        if (\count($compiledGroups) === 1) {
            return $compiledGroups[0];
        }

        $wrapped = array_map(self::wrapGroup(...), $compiledGroups);

        return '(' . implode(' AND ', $wrapped) . ')';
    }

    private static function wrapGroup(string $group): string
    {
        if (str_starts_with($group, '(') && str_ends_with($group, ')')) {
            return $group;
        }

        return '(' . $group . ')';
    }

    private function compileEntry(string $key, mixed $value): string
    {
        $parts = preg_split('/\s+/', trim($key)) ?: [];
        if ($parts === []) {
            throw new \LogicException('WHERE key cannot be empty.');
        }

        $field = (string) array_shift($parts);
        if ($field === '') {
            throw new \LogicException('WHERE key must contain a field name.');
        }

        $operator = strtoupper(implode(' ', $parts));
        if ($operator === '') {
            if ($value === null) {
                return $field . ' IS NULL';
            }

            if (\is_array($value)) {
                return $this->compileInClause($field, $value, 'IN');
            }

            $parameter = ($this->createParameter)($field, $value);

            return $field . ' = :' . $parameter;
        }

        if ($operator === '!=' || $operator === '<>') {
            if ($value === null) {
                return $field . ' IS NOT NULL';
            }

            if (\is_array($value)) {
                return $this->compileInClause($field, $value, 'NOT IN');
            }

            $parameter = ($this->createParameter)($field, $value);

            return $field . ' ' . $operator . ' :' . $parameter;
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            if (!\is_array($value)) {
                throw new \LogicException(\sprintf('WHERE "%s %s" expects array value.', $field, $operator));
            }

            return $this->compileInClause($field, $value, $operator);
        }

        if ($operator === 'BETWEEN') {
            if (!\is_array($value) || \count($value) !== 2) {
                throw new \LogicException(\sprintf('WHERE "%s BETWEEN" expects [from, to].', $field));
            }

            $bounds = array_values($value);
            $fromParam = ($this->createParameter)($field, $bounds[0]);
            $toParam = ($this->createParameter)($field, $bounds[1]);

            return $field . ' BETWEEN :' . $fromParam . ' AND :' . $toParam;
        }

        $supportedOperators = $this->dialect->getSupportedOperators();
        if (isset($supportedOperators[$operator])) {
            $parameter = ($this->createParameter)($field, $value);

            return $field . ' ' . $operator . ' :' . $parameter;
        }

        throw new \LogicException(\sprintf('Unsupported WHERE operator "%s" for field "%s".', $operator, $field));
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function compileInClause(string $field, array $values, string $keyword): string
    {
        if ($values === []) {
            return $keyword === 'NOT IN' ? '1 = 1' : '1 = 0';
        }

        $placeholders = [];
        foreach ($values as $value) {
            $parameter = ($this->createParameter)($field, $value);
            $placeholders[] = ':' . $parameter;
        }

        return $field . ' ' . $keyword . ' (' . implode(', ', $placeholders) . ')';
    }
}
