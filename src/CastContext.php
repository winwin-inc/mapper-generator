<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\annotations\Mapping;

class CastContext
{
    /**
     * @var MappingSourceField
     */
    private $sourceField;

    /**
     * @var ReflectionTypeInterface
     */
    private $castType;

    /**
     * @var Mapping
     */
    private $mapping;

    /**
     * CastContext constructor.
     *
     * @param MappingSourceField      $sourceField
     * @param ReflectionTypeInterface $castType
     * @param Mapping                 $mapping
     */
    public function __construct(MappingSourceField $sourceField, ReflectionTypeInterface $castType, Mapping $mapping)
    {
        $this->sourceField = $sourceField;
        $this->castType = $castType;
        $this->mapping = $mapping;
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
