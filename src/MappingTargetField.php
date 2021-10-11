<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\reflection\ReflectionTypeInterface;

class MappingTargetField
{
    /**
     * @var MappingTarget
     */
    private $target;

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
    private $setter;

    /**
     * @var ReflectionTypeInterface
     */
    private $type;

    public function __construct(MappingTarget $source, string $name, ?\ReflectionProperty $property, ?\ReflectionMethod $setter)
    {
        $this->target = $source;
        $this->name = $name;
        $this->property = $property;
        $this->setter = $setter;
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
        } else {
            return $var.'->'.$this->setter->getName().'('.$valueExpress.');';
        }
    }
}
