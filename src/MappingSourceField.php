<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\reflection\ReflectionTypeInterface;

class MappingSourceField
{
    /**
     * @var MappingSource
     */
    private $source;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \ReflectionProperty|null
     */
    private $property;

    /**
     * @var \ReflectionMethod|null
     */
    private $getter;

    /**
     * @var ReflectionTypeInterface
     */
    private $type;

    public function __construct(MappingSource $source, string $name, ?\ReflectionProperty $property, ?\ReflectionMethod $getter)
    {
        $this->source = $source;
        $this->name = $name;
        $this->property = $property;
        $this->getter = $getter;
    }

    /**
     * @return MappingSource
     */
    public function getSource(): MappingSource
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \ReflectionProperty|null
     */
    public function getProperty(): ?\ReflectionProperty
    {
        return $this->property;
    }

    /**
     * @return \ReflectionMethod|null
     */
    public function getGetter(): ?\ReflectionMethod
    {
        return $this->getter;
    }

    public function getType(): ReflectionTypeInterface
    {
        if (null === $this->type) {
            if (null !== $this->property) {
                $this->type = $this->source->getDocReader()->createPropertyDocBlock($this->property)->getType();
            } else {
                $this->type = $this->source->getDocReader()->createMethodDocBlock($this->getter)->getReturnType();
            }
        }

        return $this->type;
    }

    public function getValue(): string
    {
        if (null !== $this->property) {
            return '$'.$this->source->getParameterName().'->'.$this->property->getName();
        } else {
            return '$'.$this->source->getParameterName().'->'.$this->getter->getName().'()';
        }
    }
}
