<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\QueryBuilder\Expression;

/**
 * Represents a SQL expression used in SET clauses and INSERT values.
 *
 * Used to embed arbitrary SQL expressions into value positions:
 *
 *     $qb->set('balance', Expression::raw('balance - :amount'))
 *       ->setParameter('amount', 100)
 *
 *     $q6b->setValue('created_at', Expression::raw('NOW()'))
 */
final readonly class Expression
{
    public function __construct(
        public string $sql,
    ) {
    }

    /** Reference to a table column. */
    public static function column(string $name): self
    {
        return new self($name);
    }

    /** Raw SQL expression (passed through as-is). */
    public static function raw(string $sql): self
    {
        return new self($sql);
    }
}
