<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\Reader;
use kuiper\helper\Arrays;
use kuiper\serializer\DocReader;
use kuiper\serializer\DocReaderInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Reflection\ClassReflection;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\annotations\AfterMapping;
use winwin\mapper\annotations\MappingSource as MappingSourceAnnotation;
use winwin\mapper\annotations\MappingTarget as MappingTargetAnnotation;

class Mapper implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected const TAG = '['.__CLASS__.'] ';

    /**
     * @var MapperVisitor
     */
    private $mapperVisitor;

    /**
     * @var Reader
     */
    private $annotationReader;
    /**
     * @var ValueConverter
     */
    private $converter;
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var DocReaderInterface
     */
    private $docReader;

    /**
     * @var \ReflectionClass
     */
    private $mapperClass;

    /**
     * @var MappingMethod[]
     */
    private $mappingMethods;

    /**
     * @var array
     */
    private $methodBody;

    public function __construct(MapperVisitor $visitor, \ReflectionClass $mapperClass)
    {
        $this->mapperVisitor = $visitor;
        $this->annotationReader = $visitor->getAnnotationReader();
        $this->converter = $visitor->getConverter();
        $this->parser = $visitor->getParser();
        $this->docReader = new DocReader();
        $this->mapperClass = $mapperClass;
        $this->mappingMethods = [];
    }

    /**
     * @return \ReflectionClass
     */
    public function getMapperClass(): \ReflectionClass
    {
        return $this->mapperClass;
    }

    /**
     * @return Reader
     */
    public function getAnnotationReader(): Reader
    {
        return $this->annotationReader;
    }

    /**
     * @return ValueConverter
     */
    public function getConverter(): ValueConverter
    {
        return $this->converter;
    }

    /**
     * @return Parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /**
     * @return DocReaderInterface
     */
    public function getDocReader(): DocReaderInterface
    {
        return $this->docReader;
    }

    public function hasMappingMethod(string $method): bool
    {
        return isset($this->methodBody[$method]);
    }

    public function getMappingMethod(string $method): MappingMethod
    {
        if (!isset($this->mappingMethods[$method])) {
            throw new \InvalidArgumentException("Unknown mapping method $method");
        }

        return $this->mappingMethods[$method];
    }

    public function generateMethod(ClassMethod $originMethod): ClassMethod
    {
        $name = (string) $originMethod->name;
        if (!$this->hasMappingMethod($name)) {
            throw new \InvalidArgumentException('Unknown mapping method '.$this->mapperClass.'::'.$name);
        }

        return $this->methodBody[$name];
    }

    public function getClassAlias(string $className): ?string
    {
        return $this->mapperVisitor->getClassAlias($className);
    }

    public function initialize(): void
    {
        $mapperAnnotation = $this->annotationReader->getClassAnnotation($this->mapperClass, annotations\Mapper::class);
        if (null === $mapperAnnotation) {
            throw new \InvalidArgumentException($this->mapperClass->getName().' not mapper');
        }
        foreach ($this->mapperClass->getMethods() as $method) {
            $mappingMethod = $this->createMappingMethod($method);
            if (null !== $mappingMethod) {
                $this->mappingMethods[$method->getName()] = $mappingMethod;
            }
        }
        if (empty($this->mappingMethods)) {
            $this->logger->debug(static::TAG.$this->mapperClass->getName().' has no mapping method');

            return;
        }
        $class = ClassGenerator::fromReflection(new ClassReflection($this->mapperClass->getName()));
        $class->removeMethod('getInstance');
        foreach ($class->getMethods() as $method) {
            if (isset($this->mappingMethods[$method->getName()])) {
                $method->setBody($this->mappingMethods[$method->getName()]->generate());
            }
        }

        $file = new FileGenerator();
        $file->setClass($class);

        $code = $file->generate();
        try {
            $stmts = $this->parser->parse($code);
        } catch (\Exception $e) {
            $this->logger->error(static::TAG."parse {$this->mapperClass->getName()} mapper fail: ".$e->getMessage());
            foreach (explode("\n", $code) as $i => $line) {
                echo sprintf("%3d %s\n", $i + 1, $line);
            }

            return;
        }
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            /**
             * @var array
             */
            public $methods;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->methods[(string) $node->name] = $node;
                }

                return null;
            }
        };
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($stmts);
        $this->methodBody = $visitor->methods;
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
            // 返回值为 MappingTarget
            $target = new MappingTarget($this->docReader, $returnType->getName(), null);
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
        $methodName = $method->getDeclaringClass()->getName().'::'.$method->getName();
        $parameters = $method->getParameters();
        if (1 === count($parameters)) {
            $parameterType = $parameters[0]->getType();
            if (null === $parameterType || $parameterType->isBuiltin()) {
                $this->logger->debug(static::TAG."skip $methodName because parameter is not class");

                return null;
            }

            return new MappingSource($this->docReader, $parameterType->getName(), $parameters[0]->getName());
        }
        /** @var MappingSourceAnnotation|null $source */
        $source = $this->annotationReader->getMethodAnnotation($method, MappingSourceAnnotation::class);
        if (null === $source) {
            $this->logger->debug(static::TAG."skip $methodName because source parameter is specified");

            return null;
        }
        foreach ($parameters as $parameter) {
            /** @var \ReflectionParameter $parameter */
            if ($parameter->getName() === $source->value) {
                return $this->createMappingSource($parameter);
            }
        }
        $this->logger->debug(static::TAG."skip $methodName because their is not parameter match @MapSource value");

        return null;
    }

    private function getMappingSourceAndTarget(\ReflectionMethod $method): array
    {
        $methodName = $method->getDeclaringClass()->getName().'::'.$method->getName();
        $parameters = $method->getParameters();
        if (count($parameters) < 2) {
            $this->logger->debug(static::TAG."skip $methodName because there is only one parameter");

            return [null, null];
        }
        /** @var MappingSourceAnnotation|null $source */
        $source = $this->annotationReader->getMethodAnnotation($method, MappingSourceAnnotation::class);
        /** @var MappingTargetAnnotation|null $source */
        $target = $this->annotationReader->getMethodAnnotation($method, MappingTargetAnnotation::class);

        if (null === $source && null === $target) {
            $this->logger->debug(static::TAG."skip $methodName because there is not @MappingTarget or @MappingSource");

            return [null, null];
        }

        $parameters = Arrays::assoc($parameters, 'name');
        if (2 === count($parameters)) {
            if (null !== $source) {
                if (!isset($parameters[$source->value])) {
                    throw new \InvalidArgumentException("$methodName @MappingSource parameter {$source->value} does not exist");
                }
                $sourceParameter = $parameters[$source->value];
                unset($parameters[$source->value]);
                $targetParameter = array_values($parameters)[0];
            } else {
                if (!isset($parameters[$target->value])) {
                    throw new \InvalidArgumentException("$methodName @MappingTarget parameter {$target->value} does not exist");
                }
                $targetParameter = $parameters[$target->value];
                unset($parameters[$target->value]);
                $sourceParameter = array_values($parameters)[0];
            }
        } else {
            if (null === $source) {
                throw new \InvalidArgumentException("$methodName @MappingSource is missing");
            }
            if (null === $target) {
                throw new \InvalidArgumentException("$methodName @MappingTarget is missing");
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
            throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} ".'annotated with @MapSource but has no class type');
        }

        return new MappingSource($this->docReader, $type->getName(), $parameter->getName());
    }

    private function createMappingTarget(\ReflectionParameter $parameter): MappingTarget
    {
        $type = $parameter->getType();
        if (null === $type || $type->isBuiltin()) {
            $function = $parameter->getDeclaringFunction();
            $methodName = $function->getName();
            throw new \InvalidArgumentException("$methodName parameter {$parameter->getName()} ".'annotated with @MapSource but has no class type');
        }

        return new MappingTarget($this->docReader, $type->getName(), $parameter->getName());
    }

    public function getAfterMapping(MappingMethod $mappingMethod): ?\ReflectionMethod
    {
        // TODO 根据参数匹配
        foreach ($this->mapperClass->getMethods() as $method) {
            /** @var AfterMapping|null $afterMapping */
            $afterMapping = $this->annotationReader->getMethodAnnotation($method, AfterMapping::class);
            if (null !== $afterMapping && in_array($mappingMethod->getName(), $afterMapping->value, true)) {
                return $method;
            }
        }

        return null;
    }
}
