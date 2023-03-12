<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\attribute\Mapping;

class CastContext
{
    public function __construct(
        private readonly MappingSourceField $sourceField,
        private readonly ReflectionTypeInterface $castType,
        private readonly Mapping $mapping)
    {
    }

    /**
     * @return MappingSourceField
     */
    public function getSourceField(): MappingSourceField
    {
        return $this->sourceField;
    }

    public function getOriginType(): ReflectionTypeInterface
    {
        return $this->sourceField->getType();
    }

    /**
     * @return ReflectionTypeInterface
     */
    public function getCastType(): ReflectionTypeInterface
    {
        return $this->castType;
    }

    public function getValue(): string
    {
        return $this->sourceField->getValue();
    }

    /**
     * @return Mapping
     */
    public function getMapping(): Mapping
    {
        return $this->mapping;
    }
}
