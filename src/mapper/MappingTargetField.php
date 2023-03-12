<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\reflection\ReflectionTypeInterface;

class MappingTargetField
{
    private ?ReflectionTypeInterface $type = null;

    public function __construct(
        private readonly MappingTarget $target,
        private readonly string $name,
        private readonly ?\ReflectionProperty $property,
        private readonly ?\ReflectionMethod $setter)
    {
    }

    /**
     * @return MappingTarget
     */
    public function getTarget(): MappingTarget
    {
        return $this->target;
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
    public function getSetter(): ?\ReflectionMethod
    {
        return $this->setter;
    }

    public function getType(): ReflectionTypeInterface
    {
        if (null === $this->type) {
            if (null !== $this->property) {
                $this->type = $this->target->getDocReader()->createPropertyDocBlock($this->property)->getType();
            } else {
                $types = $this->target->getDocReader()->createMethodDocBlock($this->setter)->getParameterTypes();
                $this->type = array_values($types)[0];
            }
        }

        return $this->type;
    }

    public function generate(string $valueExpress): string
    {
        $var = '$'.$this->target->getParameterName();
        if (null !== $this->property) {
            return $var.'->'.$this->property->name.' = '.$valueExpress.';';
        }

        return $var.'->'.$this->setter->getName().'('.$valueExpress.');';
    }
}
