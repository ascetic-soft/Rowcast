<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast;

use AsceticSoft\Rowcast\NameConverter\NameConverterInterface;

final class PropertyMapResolver
{
    /** @var array<string, array<string, string>> */
    private static array $resolvedMaps = [];

    /**
     * @param \ReflectionClass<object> $reflectionClass
     * @return array<string, string> column => property
     */
    public function resolve(
        ?Mapping $mapping,
        \ReflectionClass $reflectionClass,
        NameConverterInterface $nameConverter,
    ): array {
        $cacheKey = $this->buildCacheKey($mapping, $reflectionClass, $nameConverter);

        if (isset(self::$resolvedMaps[$cacheKey])) {
            return self::$resolvedMaps[$cacheKey];
        }

        $result = [];
        if ($mapping !== null && !$mapping->isAutoDiscover()) {
            foreach ($mapping->getColumns() as $columnName => $propertyName) {
                if ($mapping->isIgnored($propertyName) || !$reflectionClass->hasProperty($propertyName)) {
                    continue;
                }

                $result[$columnName] = $propertyName;
            }

            return self::$resolvedMaps[$cacheKey] = $result;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            if ($mapping?->isIgnored($propertyName) === true) {
                continue;
            }

            $columnName = $mapping?->getColumnForProperty($propertyName)
                ?? $nameConverter->toColumnName($propertyName);
            $result[$columnName] = $propertyName;
        }

        return self::$resolvedMaps[$cacheKey] = $result;
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function buildCacheKey(
        ?Mapping $mapping,
        \ReflectionClass $reflectionClass,
        NameConverterInterface $nameConverter,
    ): string {
        $mappingKey = 'null';

        if ($mapping !== null) {
            $mappingKey = json_encode([
                'class' => $mapping->getClassName(),
                'table' => $mapping->getTable(),
                'auto' => $mapping->isAutoDiscover(),
                'columns' => $mapping->getColumns(),
                'ignored' => $mapping->getIgnoredProperties(),
            ], JSON_THROW_ON_ERROR);
        }

        return $reflectionClass->getName() . '|' . $nameConverter::class . '|' . $mappingKey;
    }
}
