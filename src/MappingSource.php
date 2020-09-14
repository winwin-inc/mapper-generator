<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\serializer\DocReaderInterface;

class MappingSource
{
    /**
     * @var DocReaderInterface
     */
    private $docReader;

    /**
     * @var \ReflectionClass
     */
    private $sourceClass;

    /**
     * @var string
     */
    private $parameterName;

    public function __construct(DocReaderInterface $docReader, string $sourceClass, string $parameterName)
    {
        $this->docReader = $docReader;
        $this->sourceClass = new \ReflectionClass($sourceClass);
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
    public function getSourceClass(): \ReflectionClass
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
            } else {
                $name = ucfirst($property->getName());
                foreach (['get'.$name, 'is'.$name] as $getter) {
                    if ($this->sourceClass->hasMethod($getter)) {
                        $method = $this->sourceClass->getMethod($getter);
                        $fields[$property->getName()] = new MappingSourceField($this, $property->getName(), null, $method);
                        break;
                    }
                }
            }
        }

        return $fields;
    }
}
