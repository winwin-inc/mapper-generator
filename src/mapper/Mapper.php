<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use Doctrine\Common\Annotations\Reader;
use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionDocBlockFactory;
use kuiper\reflection\ReflectionDocBlockFactoryInterface;
use PhpParser\Error as ParserError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Roave\BetterReflection\Reflection\ReflectionClass;
use winwin\mapper\attribute;
use winwin\mapper\attribute\AfterMapping;
use winwin\mapper\attribute\Builder;
use winwin\mapper\attribute\MappingSource as MappingSourceAttribute;
use winwin\mapper\attribute\MappingTarget as MappingTargetAnnotation;

class Mapper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '[' . __CLASS__ . '] ';

    private readonly ReflectionDocBlockFactoryInterface $docReader;

    private readonly ValueConverter $converter;

    /**
     * @var MappingMethod[]
     */
    private array $mappingMethods = [];

    public function __construct(
        private readonly MapperVisitor    $mapperVisitor,
        private readonly \ReflectionClass $mapperClass)
    {
        $this->converter = $this->mapperVisitor->getConverter();
        $this->docReader = ReflectionDocBlockFactory::getInstance();
    }

    /**
     * @return \ReflectionClass
     */
    public function getMapperClass(): \ReflectionClass
    {
        return $this->mapperClass;
    }

    /**
     * @return ValueConverter
     */
    public function getConverter(): ValueConverter
    {
        return $this->converter;
    }

    /**
     * @return ReflectionDocBlockFactoryInterface
     */
    public function getDocReader(): ReflectionDocBlockFactoryInterface
    {
        return $this->docReader;
    }

    public function hasMappingMethod(string $method): bool
    {
        return isset($this->mappingMethods[$method]);
    }

    public function getMethodBody(string $method): string
    {
        if (!$this->hasMappingMethod($method)) {
            throw new \InvalidArgumentException("Cannot generate method body for $method");
        }
        return $this->getMappingMethod($method)->generate();
    }

    public function getMappingMethod(string $method): MappingMethod
    {
        if (!isset($this->mappingMethods[$method])) {
            throw new \InvalidArgumentException("Unknown mapping method $method");
        }

        return $this->mappingMethods[$method];
    }

    public function getClassAlias(string $className): ?string
    {
        return $this->mapperVisitor->getClassAlias($className);
    }

    public function initialize(): void
    {
        $mapperAttributes = $this->mapperClass->getAttributes(attribute\Mapper::class);
        if (count($mapperAttributes) === 0) {
            throw new \InvalidArgumentException($this->mapperClass->getName() . ' not mapper');
        }
        foreach ($this->mapperClass->getMethods() as $method) {
            $mappingMethod = $this->createMappingMethod($method);
            if (null !== $mappingMethod) {
                $this->mappingMethods[$method->getName()] = $mappingMethod;
            }
        }
        if (empty($this->mappingMethods)) {
            $this->logger->debug(static::TAG . $this->mapperClass->getName() . ' has no mapping method');
        }
    }

    private function createMappingMethod(\ReflectionMethod $method): ?MappingMethod
    {
        if (!$method->isPublic() || $method->isStatic() || $method->isConstructor()) {
            return null;
        }
        $returnType = $method->getReturnType();
        if (null === $returnType || ($returnType->isBuiltin() && 'void' === $returnType->getName())) {
            // 返回值为空
            [$source, $target] = $this->getMappingSourceAndTarget($method);
        } elseif (!$returnType->isBuiltin()) {
            $returnClass = $returnType->getName();
            $isBuilder = count((new \ReflectionClass($returnClass))->getAttributes(Builder::class)) > 0;
            if ($isBuilder && class_exists($returnClass . 'Builder')) {
                $target = new MappingTarget($this->docReader, $returnClass . 'Builder', null, $isBuilder);
            } else {
                $target = new MappingTarget($this->docReader, $returnClass, null);
            }
            // 返回值为 MappingTarget
            $source = $this->getMappingSource($method);
        }
        if (isset($source, $target)) {
            $mappingMethod = new MappingMethod($this, $method, $source, $target);
            $mappingMethod->setLogger($this->logger);

            return $mappingMethod;
        }

        return null;
    }

    private function getMappingSource(\ReflectionMethod $method): ?MappingSource
    {
        $methodName = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        $parameters = $method->getParameters();
        if (1 === count($parameters)) {
            $parameterType = $parameters[0]->getType();
            if (null === $parameterType || $parameterType->isBuiltin()) {
                $this->logger->debug(static::TAG . "skip $methodName because parameter is not class");

                return null;
            }

            return new MappingSource($this->docReader, $parameterType->getName(), $parameters[0]->getName());
        }
        if (2 === count($parameters) && $method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if (null === $returnType || $returnType->isBuiltin()) {
                return null;
            }
            $sourceParameter = null;
            $targetParameter = null;
            foreach ($method->getParameters() as $i => $parameter) {
                $parameterType = $parameter->getType();
                if (null === $parameterType || $parameterType->isBuiltin()) {
                    return null;
                }
                if ($parameterType->getName() === $returnType->getName()) {
                    $targetParameter = $parameter;
                } else {
                    $sourceParameter = $parameter;
                }
            }
            if (isset($sourceParameter, $targetParameter)) {
                return new MappingSource($this->docReader, $sourceParameter->getType()->getName(), $sourceParameter->getName());
            }

            return null;
        }
        $sourceAttributes = $method->getAttributes(attribute\MappingSource::class);
        if (count($sourceAttributes) === 0) {
            $this->logger->debug(static::TAG . "skip $methodName because source parameter is specified");

            return null;
        }
        $source = $sourceAttributes[0]->newInstance();
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $source->value) {
                return $this->createMappingSource($parameter);
            }
        }
        $this->logger->debug(static::TAG . "skip $methodName because their is not parameter match @MapSource value");

        return null;
    }

    private function getMappingSourceAndTarget(\ReflectionMethod $method): array
    {
        $methodName = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        $parameters = $method->getParameters();
        if (count($parameters) < 2) {
            $this->logger->debug(static::TAG . "skip $methodName because there is only one parameter");

            return [null, null];
        }
        $sourceAttributes = $method->getAttributes(attribute\MappingSource::class);
        $targetAttributes = $method->getAttributes(attribute\MappingTarget::class);

        if (count($sourceAttributes) === 0 && count($targetAttributes) === 0) {
            $this->logger->debug(static::TAG . "skip $methodName because there is not @MappingTarget or @MappingSource");

            return [null, null];
        }
        $source = count($sourceAttributes) > 0 ? $sourceAttributes[0]->newInstance() : null;
        $target = count($targetAttributes) > 0 ? $targetAttributes[0]->newInstance() : null;

        $parameters = Arrays::assoc($parameters, 'name');
        if (2 === count($parameters)) {
            if (null !== $source) {
                if (!isset($parameters[$source->value])) {
                    throw new \InvalidArgumentException("$methodName attribute MappingSource parameter {$source->value} does not exist");
                }
                $sourceParameter = $parameters[$source->value];
                unset($parameters[$source->value]);
                $targetParameter = array_values($parameters)[0];
            } elseif (null !== $target) {
                if (!isset($parameters[$target->value])) {
                    throw new \InvalidArgumentException("$methodName attribute MappingTarget parameter {$target->value} does not exist");
                }
                $targetParameter = $parameters[$target->value];
                unset($parameters[$target->value]);
                $sourceParameter = array_values($parameters)[0];
            }
        } else {
            if (null === $source) {
                throw new \InvalidArgumentException("$methodName attribute MappingSource is missing");
            }
            if (null === $target) {
                throw new \InvalidArgumentException("$methodName attribute MappingTarget is missing");
            }
            $sourceParameter = $parameters[$source->value];
            $targetParameter = $parameters[$target->value];
        }

        return [
            $this->createMappingSource($sourceParameter),
            $this->createMappingTarget($targetParameter),
        ];
    }

    private function createMappingSource(\ReflectionParameter $parameter): MappingSource
    {
        $type = $parameter->getType();
        if (null === $type || $type->isBuiltin()) {
            $function = $parameter->getDeclaringFunction();
            $methodName = $function->getName();
            throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} " . 'annotated with @MapSource but has no class type');
        }

        return new MappingSource($this->docReader, $type->getName(), $parameter->getName());
    }

    private function createMappingTarget(\ReflectionParameter $parameter): MappingTarget
    {
        $type = $parameter->getType();
        if (null === $type || $type->isBuiltin()) {
            $function = $parameter->getDeclaringFunction();
            $methodName = $function->getName();
            throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} " . 'annotated with @MapSource but has no class type');
        }

        return new MappingTarget($this->docReader, $type->getName(), $parameter->getName());
    }

    public function getAfterMapping(MappingMethod $mappingMethod): ?\ReflectionMethod
    {
        // TODO 根据参数匹配
        foreach ($this->mapperClass->getMethods() as $method) {
            $afterMappingAttributes = $method->getAttributes(AfterMapping::class);
            if (count($afterMappingAttributes) > 0) {
                $afterMapping = $afterMappingAttributes[0]->newInstance();
                if (in_array($mappingMethod->getName(), $afterMapping->value, true)) {
                    return $method;
                }
            }
        }

        return null;
    }
}
