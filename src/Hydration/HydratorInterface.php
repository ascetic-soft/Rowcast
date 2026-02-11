<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Hydration;

use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

interface HydratorInterface
{
    /**
     * Hydrate a single database row into a DTO object.
     *
     * @template T of object
     *
     * @param class-string<T>      $className Target DTO class
     * @param array<string, mixed>  $row       Column-value pairs from the database
     * @param ResultSetMapping|null $rsm       Explicit column-to-property mapping (auto mode when null)
     *
     * @return T
     */
    public function hydrate(string $className, array $row, ?ResultSetMapping $rsm = null): object;

    /**
     * Hydrate multiple database rows into DTO objects.
     *
     * @template T of object
     *
     * @param class-string<T>                   $className Target DTO class
     * @param list<array<string, mixed>>         $rows      List of column-value pairs from the database
     * @param ResultSetMapping|null              $rsm       Explicit column-to-property mapping (auto mode when null)
     *
     * @return list<T>
     */
    public function hydrateAll(string $className, array $rows, ?ResultSetMapping $rsm = null): array;
}
