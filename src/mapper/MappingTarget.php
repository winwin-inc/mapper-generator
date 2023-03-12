<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\reflection\ReflectionDocBlockFactoryInterface;
use ReflectionClass;

class MappingTarget
{

    private readonly ReflectionClass $targetClass;

    private ?string $variableName = null;

    public function __construct(
        private readonly ReflectionDocBlockFactoryInterface $docReader,
        string $targetClass,
        private readonly ?string $parameterName,
        private readonly bool $builder = false)
    {
        $this->targetClass = new ReflectionClass($targetClass);
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
    public function getTargetClass(): ReflectionClass
    {
        return $this->targetClass;
    }

    /**
     * @return string|null
     */
    public function getParameterName(): ?string
    {
        return $this->variableName ?? $this->parameterName;
    }

    public function isParameter(): bool
    {
        return isset($this->parameterName);
    }

    /**
     * @return bool
     */
    public function isBuilder(): bool
    {
        return $this->builder;
    }

    /**
     * @param string|null $variableName
     */
    public function setVariableName(?string $variableName): void
    {
        $this->variableName = $variableName;
    }

    /**
     * @return MappingTargetField[]
     *
     * @throws \ReflectionException
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->targetClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            if ($property->isPublic()) {
                $fields[$property->getName()] = new MappingTargetField($this, $property->getName(), $property, null);
            }
        }
        foreach ($this->targetClass->getMethods() as $method) {
            if (!$method->isPublic() || $method->isStatic() || 1 !== count($method->getParameters())) {
                continue;
            }
            if (preg_match('/^set(.*)$/', $method->getName(), $matches)) {
                $name = lcfirst($matches[1]);
                if (!isset($fields[$name])) {
                    $fields[$name] = new MappingTargetField($this, $name, null, $method);
                }
            }
        }

        return $fields;
    }
}
