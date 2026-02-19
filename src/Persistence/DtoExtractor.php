<?php

declare(strict_types=1);

namespace AsceticSoft\Rowcast\Persistence;

use AsceticSoft\Rowcast\Mapping\NameConverter\NameConverterInterface;
use AsceticSoft\Rowcast\Mapping\NameConverter\SnakeCaseToCamelCaseConverter;
use AsceticSoft\Rowcast\Mapping\ResultSetMapping;

/**
 * Extracts column => value pairs from a DTO object via Reflection,
 * converting PHP values to DB-compatible format through ValueConverterInterface.
 */
final readonly class DtoExtractor
{
    private NameConverterInterface $nameConverter;
    private ValueConverterInterface $valueConverter;

    public function __construct(
        ?NameConverterInterface  $nameConverter = null,
        ?ValueConverterInterface $valueConverter = null,
    ) {
        $this->nameConverter = $nameConverter ?? new SnakeCaseToCamelCaseConverter();
        $this->valueConverter = $valueConverter ?? ValueConverterRegistry::createDefault();
    }

    /**
     * Extract column => value pairs from a DTO.
     *
     * In RSM mode, only mapped fields are extracted.
     * In auto mode, all properties are extracted with NameConverter-derived column names.
     * Uninitialized properties are skipped.
     *
     * @return array<string, mixed>
     */
    public function extract(object $dto, ?ResultSetMapping $rsm = null): array
    {
        $reflClass = new \ReflectionClass($dto);
        $data = [];

        if ($rsm !== null) {
            foreach ($rsm->getFields() as $columnName => $propertyName) {
                if (!$reflClass->hasProperty($propertyName)) {
                    continue;
                }

                $reflProperty = $reflClass->getProperty($propertyName);

                if (!$reflProperty->isInitialized($dto)) {
                    continue;
                }

                $data[$columnName] = $this->valueConverter->convertForDb($reflProperty->getValue($dto));
            }
        } else {
            foreach ($reflClass->getProperties() as $reflProperty) {
                if (!$reflProperty->isInitialized($dto)) {
                    continue;
                }

                $columnName = $this->nameConverter->toColumnName($reflProperty->getName());
                $data[$columnName] = $this->valueConverter->convertForDb($reflProperty->getValue($dto));
            }
        }

        return $data;
    }
}
