<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\reflection\ReflectionDocBlockFactoryInterface;
use ReflectionClass;

class MappingSource
{

    private readonly ReflectionClass $sourceClass;

    public function __construct(
        private readonly ReflectionDocBlockFactoryInterface $docReader,
        string $sourceClass,
        private readonly string $parameterName)
    {
        $this->sourceClass = new ReflectionClass($sourceClass);
    }

    /**
     * @return ReflectionDocBlockFactoryInterface
     */
    public function getDocReader(): ReflectionDocBlockFactoryInterface
    {
        return $this->docReader;
    }

    /**
     * @return ReflectionClass
     */
    public function getSourceClass(): ReflectionClass
    {
        return $this->sourceClass;
    }

    /**
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * @return MappingSourceField[]
     *
     * @throws \ReflectionException
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->sourceClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if ($property->isPublic()) {
                $fields[$property->getName()] = new MappingSourceField($this, $property->getName(), $property, null);
            }
        }
        foreach ($this->sourceClass->getMethods() as $method) {
            if (!$method->isPublic() || $method->isStatic() || count($method->getParameters()) > 0) {
                continue;
            }
            if (preg_match('/^(get|is|has)(.*)$/', $method->getName(), $matches)) {
                $name = lcfirst($matches[2]);
                if (!isset($fields[$name])) {
                    $fields[$name] = new MappingSourceField($this, $name, null, $method);
                }
            }
        }

        return $fields;
    }
}
