<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\reflection\ReflectionTypeInterface;

class MappingSourceField
{
    private ?ReflectionTypeInterface $type = null;

    public function __construct(
        private readonly MappingSource $source,
        private readonly string $name,
        private readonly ?\ReflectionProperty $property,
        private readonly ?\ReflectionMethod $getter)
    {
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
        }

        return '$'.$this->source->getParameterName().'->'.$this->getter->getName().'()';
    }
}
