<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionType;
use kuiper\reflection\ReflectionTypeInterface;
use kuiper\reflection\type\MixedType;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\attribute\InheritConfiguration;
use winwin\mapper\attribute\InheritInverseConfiguration;
use winwin\mapper\attribute\Mapping;
use winwin\mapper\attribute\MappingIgnore;

class MappingMethod implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var string[]
     */
    private array $variables;

    /**
     * @var string[]
     */
    private array $codes;

    /**
     * @var Mapping[]
     */
    private ?array $mappings = null;

    public function __construct(
        private readonly Mapper $mapper, 
        private readonly \ReflectionMethod $method,
        private readonly MappingSource $source,
        private readonly MappingTarget $target)
    {
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
            $mappings = [];
            $mappingAttributes = $this->method->getAttributes(Mapping::class);
            foreach ($mappingAttributes as $mappingAttribute) {
                $mapping = $mappingAttribute->newInstance();
                $mappings[$mapping->target] = $mapping;
            }
            foreach ($this->method->getAttributes(MappingIgnore::class) as $mappingIgnoreAttribute) {
                $mappingIgnore = $mappingIgnoreAttribute->newInstance();
                foreach ($mappingIgnore->value as $name) {
                    $mappings[$name] = false;
                }
            }
            $inheritAttributes = $this->method->getAttributes(InheritConfiguration::class);
            if (count($inheritAttributes) > 0) {
                $inherit = $inheritAttributes[0]->newInstance();
                try {
                    $inheritMethod = $this->mapper->getMappingMethod($inherit->value);
                    foreach ($inheritMethod->getMappings() as $key => $mapping) {
                        if (!isset($mappings[$key])) {
                            $mappings[$key] = $mapping;
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} attribute InheritConfiguration method {$inherit->value} not found");
                }
            }
            $inverseAttributes = $this->method->getAttributes(InheritInverseConfiguration::class);
            if (count($inverseAttributes) > 0) {
                $inverse = $inverseAttributes[0]->newInstance();
                try {
                    foreach ($this->mapper->getMappingMethod($inverse->value)->getMappings() as $key => $mapping) {
                        if (!isset($mappings[$mapping->source]) && $this->canInheritInverseMapping($mapping)) {
                            $mappings[$mapping->source] = new Mapping(
                                target: $mapping->source,
                                source: $mapping->target, 
                                dateFormat: $mapping->dateFormat,
                                numberFormat: $mapping->numberFormat,
                                constant: $mapping->constant,
                                defaultValue: $mapping->defaultValue,
                                expression: $mapping->expression,
                                condition: $mapping->condition,
                                qualifiedByName: $mapping->qualifiedByName,
                            );
                        }
                    }
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("{$this->getMethodName()} attribute InheritConfiguration method {$inverse->value} not found");
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
                    $mapping = new Mapping(
                        target: $field->getName(),
                        source: $field->getName(),
                    );
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
                    throw new \InvalidArgumentException("{$this->getMethodName()} mapping '{$field->getName()}' source '{$mapping->source}' does not exist");
                }
                $sourceField = $sourceFields[$mapping->source];
            }
            $this->generateMappingCode($field, $sourceField, $mapping);
        }
        if (!empty($missing)) {
            $this->logger->error(static::TAG."{$this->getMethodName()} has unmapping fields, ".
                                 'dismiss error by add @MappingIgnore({'.substr(json_encode($missing), 1, -1).'})');
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
    }

    private function generateMappingCode(MappingTargetField $field, ?MappingSourceField $sourceField, Mapping $mapping): void
    {
        $sourceAllowsNull = true;
        if (null !== $sourceField) {
            $valueExpression = $this->generateTargetValueExpression($field, $sourceField, $mapping);
            $sourceAllowsNull = $sourceField->getType()->allowsNull();
        } else {
            $mappingType = $this->getMappingValueType($mapping);
            if (null !== $mappingType) {
                $sourceAllowsNull = $mappingType->allowsNull();
            }
            $valueExpression = $this->generateValueExpression($field, $mapping);
        }
        if (null !== $mapping->condition) {
            $this->codes[] = 'if ('.$this->generateCondition($mapping->condition, $valueExpression).') {';
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
            if ($sourceAllowsNull) {
                $this->codes[] = 'if (null !== $'.$var.') {';
                $this->codes[] = $field->generate('$'.$var);
                $this->codes[] = '}';
            } else {
                $this->codes[] = $field->generate('$'.$var);
            }
        }
        if (null !== $mapping->condition) {
            $this->codes[] = '}';
        }
    }

    private function generateTargetValueExpression(MappingTargetField $field, MappingSourceField $sourceField, Mapping $mapping): string
    {
        if (null !== $mapping->qualifiedByName) {
            $method = $this->mapper->getMapperClass()->getMethod($mapping->qualifiedByName);
            $reflectionParameters = array_values(array_filter($method->getParameters(),
                static function (\ReflectionParameter $param): bool {
                    return !$param->isOptional();
                }));
            if (1 !== count($reflectionParameters)) {
                throw new \InvalidArgumentException($this->mapper->getMapperClass()->getName().'::'.$mapping->qualifiedByName.' should contain only one parameter');
            }
            $type = $reflectionParameters[0]->getType();
            if (null !== $type && $type->allowsNull()) {
                return '$this->'.$mapping->qualifiedByName.'('.$sourceField->getValue().')';
            }

            $var = $this->generateVariableName($field->getName());
            if ($sourceField->getType()->allowsNull()) {
                $this->codes[] = sprintf('$%s = null;', $var);
                $this->codes[] = sprintf('if (null !== %s) {', $sourceField->getValue());
                $this->codes[] = sprintf('$%s = $this->%s(%s);', $var, $mapping->qualifiedByName, $sourceField->getValue());
                $this->codes[] = '}';
            } else {
                $this->codes[] = sprintf('$%s = $this->%s(%s);', $var, $mapping->qualifiedByName, $sourceField->getValue());
            }

            return '$'.$var;
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
            $reflectionParameters = array_values(array_filter($method->getParameters(),
                static function (\ReflectionParameter $parameter): bool {
                    return !$parameter->isDefaultValueAvailable();
                }));
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
        $returnType = $this->method->getReturnType();
        if (null !== $returnType && 'void' !== $returnType->getName()) {
            $this->codes[] = 'return $'.$this->target->getParameterName().($this->target->isBuilder() ? '->build()' : '').';';
        }
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
        $params = array_values($this->mapper->getDocReader()->createMethodDocBlock($afterMappingMethod)->getParameterTypes());
        if (2 !== count($params)) {
            throw new \InvalidArgumentException(sprintf('%s::%s should contain only 2 parameter %s and %s', $afterMappingMethod->getDeclaringClass()->getName(), $afterMappingMethod->getName(), $this->source->getSourceClass()->getName(), $this->target->getTargetClass()->getName()));
        }
        if ($params[0]->getName() === $this->source->getSourceClass()->getName()) {
            $this->codes[] = sprintf('$this->%s($%s, $%s);', $afterMappingMethod->getName(), $this->source->getParameterName(), $this->target->getParameterName());
        } else {
            $this->codes[] = sprintf('$this->%s($%s, $%s);', $afterMappingMethod->getName(), $this->target->getParameterName(), $this->source->getParameterName());
        }
    }

    private function generateCondition(string $condition, string $valueExpression): string
    {
        if (str_contains($condition, '%s')) {
            return sprintf($condition, $valueExpression);
        }

        return $valueExpression.$condition;
    }

    private function getMappingValueType(Mapping $mapping): ?ReflectionTypeInterface
    {
        if (null !== $mapping->constant || null !== $mapping->defaultValue) {
            return new MixedType();
        }
        if (null !== $mapping->qualifiedByName) {
            $method = $this->mapper->getMapperClass()->getMethod($mapping->qualifiedByName);
            $type = $method->getReturnType();
            if (null !== $type) {
                return ReflectionType::fromPhpType($type);
            }
        }

        return null;
    }
}
