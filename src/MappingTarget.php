<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\serializer\DocReaderInterface;

class MappingTarget
{
    /**
     * @var DocReaderInterface
     */
    private $docReader;

    /**
     * @var \ReflectionClass
     */
    private $targetClass;

    /**
     * @var string|null
     */
    private $parameterName;

    /**
     * @var string|null
     */
    private $variableName;

    public function __construct(DocReaderInterface $docReader, string $targetClass, ?string $parameterName)
    {
        $this->docReader = $docReader;
        $this->targetClass = new \ReflectionClass($targetClass);
        $this->parameterName = $parameterName;
    }

    /**
     * @return DocReaderInterface
     */
    public function getDocReader(): DocReaderInterface
    {
        return $this->docReader;
    }

    /**
     * @return \ReflectionClass
     */
    public function getTargetClass(): \ReflectionClass
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
