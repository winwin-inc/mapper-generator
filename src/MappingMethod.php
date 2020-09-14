<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionTypeInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\annotations\Mapping;
use winwin\mapper\annotations\MappingIgnore;

class MappingMethod implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var \ReflectionMethod
     */
    private $method;

    /**
     * @var MappingSource
     */
    private $source;

    /**
     * @var MappingTarget
     */
    private $target;

    /**
     * @var string[]
     */
    private $variables;

    /**
     * @var string[]
     */
    private $codes;

    public function __construct(Mapper $mapper, \ReflectionMethod $method, MappingSource $source, MappingTarget $target)
    {
        $this->mapper = $mapper;
        $this->method = $method;
        $this->source = $source;
        $this->target = $target;
        $this->variables = Arrays::pull($method->getParameters(), 'name');
    }

    public function generate(): string
    {
        if (!$this->target->isParameter()) {
            $this->generateNewTarget();
        }
        $this->generateMapping();
        $this->generateReturn();

        return implode("\n", $this->codes);
    }

    private function generateMapping(): void
    {
        $annotations = $this->mapper->getAnnotationReader()->getMethodAnnotations($this->method);
        $mappings = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Mapping) {
                $mappings[$annotation->target] = $annotation;
            } elseif ($annotation instanceof MappingIgnore) {
                foreach ($annotation->value as $name) {
                    $mappings[$annotation->name] = false;
                }
            }
        }
        $sourceFields = Arrays::assoc($this->source->getFields(), 'name');
        $missing = [];
        foreach ($this->target->getFields() as $field) {
            if (!isset($mappings[$field->getName()])) {
                if (isset($sourceFields[$field->getName()])) {
                    $mapping = new Mapping();
                    $mapping->source = $field->getName();
                    $mapping->target = $field->getName();
                    $mappings[$field->getName()] = $mapping;
                } else {
                    $missing[] = $field->getName();
                    continue;
                }
            }
            if (false === $mappings[$field->getName()]) {
                continue;
            }
            /** @var Mapping $mapping */
            $mapping = $mappings[$field->getName()];
            $sourceField = null;
            if (null !== $mapping->target) {
                if (!isset($sourceFields[$mapping->source])) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} mapping '{$field->getName()}' target {$mapping->target} does not exist");
                }
                $sourceField = $sourceFields[$mapping->source];
            }
            $this->generateMappingCode($field, $sourceField, $mapping);
        }
        if (!empty($missing)) {
            $this->logger->warning(static::TAG."{$this->getMethodName()} has unmapping fields ".implode(',', $missing));
        }
    }

    public function getMethodName(): string
    {
        return $this->method->getDeclaringClass()->getName().'::'.$this->method->getName();
    }

    private function generateNewTarget(): void
    {
        $this->target->setVariableName($this->generateVariableName(lcfirst($this->target->getTargetClass()->getShortName())));
        $this->codes[] = "\${$this->target->getParameterName()} = new \\{$this->target->getTargetClass()->getName()}();";
    }

    private function generateVariableName(string $name): string
    {
        $i = '';
        while (true) {
            if (!in_array($name.$i, $this->variables, true)) {
                $this->variables[] = $name.$i;

                return $name.$i;
            }
            if ('' === $i) {
                $i = 0;
            }
            ++$i;
        }
        throw new \LogicException('Cannot generate parameter name');
    }

    private function generateMappingCode(MappingTargetField $field, ?MappingSourceField $sourceField, Mapping $mapping): void
    {
        if (null !== $sourceField) {
            $valueExpression = $this->generateTargetValueExpression($field, $sourceField, $mapping);
        } else {
            $valueExpression = $this->generateValueExpression($field, $mapping);
        }
        if ($field->getType()->allowsNull()) {
            $this->codes[] = $field->generate($valueExpression);
        } else {
            if (preg_match('/\$\w+/', $valueExpression)) {
                $var = substr($valueExpression, 1);
            } else {
                $var = $this->generateVariableName($field->getName());
                $this->codes[] = '$'.$var.' = '.$valueExpression.';';
            }
            $this->codes[] = 'if ($'.$var.' !== null ) {';
            $this->codes[] = $field->generate('$'.$var);
            $this->codes[] = '}';
        }
    }

    private function generateTargetValueExpression(MappingTargetField $field, MappingSourceField $sourceField, Mapping $mapping): string
    {
        if (null !== $mapping->qualifiedByName) {
            $method = $this->mapper->getMapperClass()->getMethod($mapping->qualifiedByName);
            $reflectionParameters = $method->getParameters();
            if (1 !== count($reflectionParameters)) {
                throw new \InvalidArgumentException($this->mapper->getMapperClass()->getName().'::'.$mapping->qualifiedByName.' should contain only one parameter');
            }
            $type = $reflectionParameters[0]->getType();
            if ($type->allowsNull()) {
                return '$this->'.$mapping->qualifiedByName.'('.$sourceField->getValue().')';
            } else {
                $var = $this->generateVariableName($field->getName());
                $this->codes[] = sprintf('$%s = null;', $var);
                $this->codes[] = sprintf('if (%s !== null) {', $sourceField->getValue());
                $this->codes[] = sprintf('$%s = $this->%s(%s);', $var, $mapping->qualifiedByName, $sourceField->getValue());
                $this->codes[] = '}';

                return '$'.$var;
            }
        }
        if ($this->typeEquals($field->getType(), $sourceField->getType())) {
            return $sourceField->getValue();
        }
        try {
            return $this->mapper->getConverter()->convert(new CastContext($sourceField, $field->getType(), $mapping));
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("{$this->getMethodName()} field {$field->getName()} convert fail: ".$e->getMessage());
        }
    }

    private function generateValueExpression(MappingTargetField $field, Mapping $mapping): string
    {
        if (null !== $mapping->expression) {
            return $mapping->expression;
        }
        if (null !== $mapping->constant) {
            return $mapping->constant[0].'::'.$mapping->constant[1];
        }
        if (null !== $mapping->defaultValue) {
            return var_export($mapping->defaultValue, true);
        }
        if (null !== $mapping->qualifiedByName) {
            $method = $this->mapper->getMapperClass()->getMethod($mapping->qualifiedByName);
            $reflectionParameters = $method->getParameters();
            if (1 === count($reflectionParameters)
                && null !== $reflectionParameters[0]->getType()
                && $reflectionParameters[0]->getType()->getName() === $this->source->getSourceClass()->getName()) {
                return '$this->'.$mapping->qualifiedByName.'($'.$this->source->getParameterName().')';
            }
            throw new \InvalidArgumentException($this->mapper->getMapperClass()->getName().'::'.$mapping->qualifiedByName.' should contain only one parameter with type '.$this->source->getSourceClass()->getName());
        }
        throw new \InvalidArgumentException("Unknown mapping for {$this->getMethodName()} field {$field->getName()}");
    }

    private function typeEquals(ReflectionTypeInterface $aType, ReflectionTypeInterface $bType): bool
    {
        return $aType->getName() === $bType->getName();
    }

    private function generateReturn(): void
    {
        $this->codes[] = 'return $'.$this->target->getParameterName().';';
    }
}
