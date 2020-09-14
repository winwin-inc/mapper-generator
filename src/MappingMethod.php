<?php

declare(strict_types=1);

namespace winwin\mapper;

use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionTypeInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\annotations\InheritConfiguration;
use winwin\mapper\annotations\InheritInverseConfiguration;
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

    /**
     * @var Mapping[]
     */
    private $mappings;

    public function __construct(Mapper $mapper, \ReflectionMethod $method, MappingSource $source, MappingTarget $target)
    {
        $this->mapper = $mapper;
        $this->method = $method;
        $this->source = $source;
        $this->target = $target;
        $this->variables = Arrays::pull($method->getParameters(), 'name');
    }

    public function getName(): string
    {
        return $this->method->getName();
    }

    public function generate(): string
    {
        if (!$this->target->isParameter()) {
            $this->generateNewTarget();
        }
        $this->generateMapping();
        $this->generateAfterMapping();
        $this->generateReturn();

        return implode("\n", $this->codes);
    }

    public function getMappings(): array
    {
        if (null === $this->mappings) {
            $annotations = $this->mapper->getAnnotationReader()->getMethodAnnotations($this->method);
            $mappings = [];
            foreach ($annotations as $annotation) {
                if ($annotation instanceof Mapping) {
                    $mappings[$annotation->target] = $annotation;
                } elseif ($annotation instanceof MappingIgnore) {
                    foreach ($annotation->value as $name) {
                        $mappings[$name] = false;
                    }
                }
            }
            /** @var InheritConfiguration $inherit */
            $inherit = $this->mapper->getAnnotationReader()->getMethodAnnotation($this->method, InheritConfiguration::class);
            if (null !== $inherit) {
                try {
                    foreach ($this->mapper->getMappingMethod($inherit->value)->getMappings() as $key => $mapping) {
                        if (!isset($mappings[$key])) {
                            $mappings[$key] = $mapping;
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} @InheritConfiguration method {$inherit->value} not found");
                }
            }
            /** @var InheritInverseConfiguration $inverse */
            $inverse = $this->mapper->getAnnotationReader()->getMethodAnnotation($this->method, InheritInverseConfiguration::class);
            if (null !== $inverse) {
                try {
                    foreach ($this->mapper->getMappingMethod($inverse->value)->getMappings() as $key => $mapping) {
                        if ($this->canInheritInverseMapping($mapping) && !isset($mappings[$mapping->source])) {
                            $invert = clone $mapping;
                            $invert->target = $mapping->source;
                            $invert->source = $mapping->target;
                            $mappings[$invert->target] = $invert;
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} @InheritConfiguration method {$inherit->value} not found");
                }
            }
            $this->mappings = $mappings;
        }

        return $this->mappings;
    }

    private function generateMapping(): void
    {
        $mappings = $this->getMappings();
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
            if (null !== $mapping->source) {
                if (!isset($sourceFields[$mapping->source])) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} mapping '{$field->getName()}' target {$mapping->target} does not exist");
                }
                $sourceField = $sourceFields[$mapping->source];
            }
            $this->generateMappingCode($field, $sourceField, $mapping);
        }
        if (!empty($missing)) {
            $this->logger->error(static::TAG."{$this->getMethodName()} has unmapping fields ".implode(',', $missing));
        }
    }

    public function getMethodName(): string
    {
        return $this->method->getDeclaringClass()->getName().'::'.$this->method->getName();
    }

    private function generateNewTarget(): void
    {
        $this->target->setVariableName($this->generateVariableName($this->target->getTargetClass()->getName()));
        $this->codes[] = "\${$this->target->getParameterName()} = new \\{$this->target->getTargetClass()->getName()}();";
    }

    private function generateVariableName(string $name): string
    {
        $alias = $this->mapper->getClassAlias($name);
        if (null !== $alias) {
            $name = $alias;
        } else {
            $parts = explode('\\', $name);
            $name = end($parts);
        }
        $name = lcfirst($name);
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
        if (null !== $mapping->condition) {
            $this->codes[] = 'if ('.$valueExpression.$mapping->condition.') {';
        }
        if ($field->getType()->allowsNull()) {
            $this->codes[] = $field->generate($valueExpression);
        } else {
            if (preg_match('/^\$\w+$/', $valueExpression)) {
                $var = substr($valueExpression, 1);
            } else {
                $var = $this->generateVariableName($field->getName());
                $this->codes[] = '$'.$var.' = '.$valueExpression.';';
            }
            $this->codes[] = 'if ($'.$var.' !== null ) {';
            $this->codes[] = $field->generate('$'.$var);
            $this->codes[] = '}';
        }
        if (null !== $mapping->condition) {
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
        if ($aType->getName() === $bType->getName()) {
            return true;
        }
        if ($aType->isArray() && $bType->isArray()) {
            return true;
        }

        return false;
    }

    private function generateReturn(): void
    {
        $this->codes[] = 'return $'.$this->target->getParameterName().';';
    }

    /**
     * @param mixed $mapping
     *
     * @return bool
     */
    private function canInheritInverseMapping($mapping): bool
    {
        if ($mapping instanceof Mapping) {
            $keys = array_keys(Arrays::filter(get_object_vars($mapping)));

            return 2 === count($keys) && 0 === count(array_diff($keys, ['source', 'target']));
        }

        return false;
    }

    private function generateAfterMapping(): void
    {
        $afterMappingMethod = $this->mapper->getAfterMapping($this);
        if (null === $afterMappingMethod) {
            return;
        }
        /** @var ReflectionTypeInterface[] $params */
        $params = array_values($this->mapper->getDocReader()->getParameterTypes($afterMappingMethod));
        if (2 !== count($params)) {
            throw new \InvalidArgumentException(sprintf('%s::%s should contain only 2 parameter %s and %s', $afterMappingMethod->getDeclaringClass()->getName(), $afterMappingMethod->getName(), $this->source->getSourceClass()->getName(), $this->target->getTargetClass()->getName()));
        }
        if ($params[0]->getName() === $this->source->getSourceClass()->getName()) {
            $this->codes[] = sprintf('$this->%s($%s, $%s);', $afterMappingMethod->getName(), $this->source->getParameterName(), $this->target->getParameterName());
        } else {
            $this->codes[] = sprintf('$this->%s($%s, $%s);', $afterMappingMethod->getName(), $this->target->getParameterName(), $this->source->getParameterName());
        }
    }
}
