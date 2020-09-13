<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Doctrine\Common\Annotations\Reader;
use kuiper\helper\Arrays;
use PhpParser\Node\Stmt\ClassMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use wenbinye\mapper\annotations\MappingSource;
use wenbinye\mapper\annotations\MappingTarget;

class Mapper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var \ReflectionClass
     */
    private $mapperClass;

    /**
     * @var MappingMethod[]
     */
    private $mappingMethods;

    /**
     * Mapper constructor.
     *
     * @param Reader           $annotationReader
     * @param \ReflectionClass $mapperClass
     */
    public function __construct(Reader $annotationReader, \ReflectionClass $mapperClass)
    {
        $this->annotationReader = $annotationReader;
        $this->mapperClass = $mapperClass;
        $this->mappingMethods = [];
        $this->initialize();
    }

    /**
     * @return Reader
     */
    public function getAnnotationReader(): Reader
    {
        return $this->annotationReader;
    }

    public function hasMappingMethod(string $method): bool
    {
        return isset($this->mappingMethods[$method]);
    }

    public function generateMethod(ClassMethod $originMethod): ClassMethod
    {
        $name = (string) $originMethod->name;
        if (!$this->hasMappingMethod($name)) {
            throw new \InvalidArgumentException('Unknown mapping method '.$this->mapperClass.'::'.$name);
        }

        return $this->mappingMethods[$name]->generate($originMethod);
    }

    private function initialize(): void
    {
        $mapperAnnotation = $this->annotationReader->getClassAnnotation($this->mapperClass, annotations\Mapper::class);
        if (null === $mapperAnnotation) {
            throw new \InvalidArgumentException($this->mapperClass->getName().' not mapper');
        }
        foreach ($this->mapperClass->getMethods() as $method) {
            $mappingMethod = $this->createMappingMethod($method);
            if (null !== $mappingMethod) {
                $mappingMethod->setLogger($this->logger);
                $this->mappingMethods[$method->getName()] = $mappingMethod;
            }
        }
    }

    private function createMappingMethod(\ReflectionMethod $method): ?MappingMethod
    {
        $methodName = $method->getDeclaringClass()->getName().'::'.$method->getName();
        if (!$method->isPublic() || $method->isStatic()) {
            if (!$method->isPublic()) {
                $this->logger->debug(static::TAG."skip $methodName because not public");
            } else {
                $this->logger->debug(static::TAG."skip $methodName because it is static");
            }

            return null;
        }
        $parameters = $method->getParameters();
        $returnType = $method->getReturnType();
        $mappingTargetClass = null;
        $mappingSourceClass = null;
        if (null === $returnType || ($returnType->isBuiltin() && 'void' === $returnType->getName())) {
            // 返回值为空
            $updateTarget = true;
            [$mappingTargetClass, $mappingSourceClass] = $this->getMappingSourceAndTarget($parameters);
        } elseif (null !== $returnType && !$returnType->isBuiltin()) {
            // 返回值为 MappingTarget
            $updateTarget = false;
            $mappingTargetClass = $returnType->getName();
            $mappingSourceClass = $this->getMappingSource($parameters);
        }
        if (isset($mappingSourceClass, $mappingTargetClass)) {
            return new MappingMethod($this, $method, $mappingSourceClass, $mappingTargetClass, $updateTarget);
        }

        return null;
    }

    private function getMappingSource(array $parameters): ?string
    {
        if (1 === count($parameters)) {
            $parameterType = $parameters[0]->getType();
            if (null === $parameterType || $parameterType->isBuiltin()) {
                $this->logger->debug(static::TAG."skip $methodName because parameter is not class");

                return null;
            }

            return $parameterType->getName();
        }
        /** @var MappingSource|null $source */
        $source = $this->annotationReader->getMethodAnnotation($method, MappingSource::class);
        if (null === $source) {
            $this->logger->debug(static::TAG."skip $methodName because source parameter is specified");

            return null;
        }
        foreach ($parameters as $parameter) {
            /** @var \ReflectionParameter $parameter */
            if ($parameter->getName() === $source->value) {
                $type = $parameter->getType();
                if (null === $type || $type->isBuiltin()) {
                    throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} ".'annotated with @MapSource but has no class type');
                }

                return $type->getName();
            }
        }
        $this->logger->debug(static::TAG."skip $methodName because their is not parameter match @MapSource value");

        return null;
    }

    private function getMappingSourceAndTarget(array $parameters): array
    {
        if (count($parameters) < 2) {
            $this->logger->debug(static::TAG."skip $methodName because there is only one parameter");

            return [null, null];
        }
        /** @var MappingSource|null $source */
        $source = $this->annotationReader->getMethodAnnotation($method, MappingSource::class);
        /** @var MappingTarget|null $source */
        $target = $this->annotationReader->getMethodAnnotation($method, MappingTarget::class);

        if (null === $source && null === $target) {
            $this->logger->debug(static::TAG."skip $methodName because there is not @MappingTarget or @MappingSource");
        }

        $parameters = Arrays::assoc($parameters, 'name');
        if (2 === count($parameters)) {
            return $parameterType->getName();
        }
        if (null === $source) {
            $this->logger->debug(static::TAG."skip $methodName because source parameter is specified");

            return null;
        }
        foreach ($parameters as $parameter) {
            /** @var \ReflectionParameter $parameter */
            if ($parameter->getName() === $source->value) {
                $type = $parameter->getType();
                if (null === $type || $type->isBuiltin()) {
                    throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} ".'annotated with @MapSource but has no class type');
                }

                return $type->getName();
            }
        }
        $this->logger->debug(static::TAG."skip $methodName because their is not parameter match @MapSource value");

        return null;
    }
}
