<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\Reader;
use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionType;
use kuiper\reflection\ReflectionTypeInterface;
use kuiper\web\view\PhpView;
use PhpParser\Node;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;
use winwin\mapper\annotations\BuilderIgnore;

class BuilderTarget
{
    private const SORT_PROPERTY = 100;
    private const SORT_METHOD = 1000;

    /**
     * @var ReflectionClass
     */
    private $targetClass;

    /**
     * @var ReflectionClass|null
     */
    private $builderClass;

    /**
     * @var ReflectionProperty[]
     */
    private $properties;

    /**
     * @var PhpView
     */
    private $view;

    public static function create(Reader $annotationReader, ReflectionClass $class): self
    {
        $reflClass = new \ReflectionClass($class->getName());
        $builderTarget = new self();
        $builderTarget->view = new PhpView(__DIR__.'/templates');
        $builderTarget->targetClass = $class;
        $builderTarget->properties = array_values(array_filter($class->getProperties(), static function (ReflectionProperty $prop) use ($reflClass, $annotationReader) {
            if ($prop->isStatic()) {
                return false;
            }
            if (null !== $annotationReader->getPropertyAnnotation($reflClass->getProperty($prop->getName()), BuilderIgnore::class)) {
                return false;
            }

            return true;
        }));

        return $builderTarget;
    }

    /**
     * @return ReflectionProperty[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getClassShortName(): string
    {
        return $this->targetClass->getShortName();
    }

    public function setBuilderClass(?ReflectionClass $builderClass): void
    {
        $this->builderClass = $builderClass;
    }

    public function getBuilderClassName(): string
    {
        return $this->targetClass->getNamespaceName().'\\'.$this->getBuilderClassShortName();
    }

    public function getBuilderClassShortName(): string
    {
        return $this->targetClass->getShortName().'Builder';
    }

    public function getBuilderClassNamespaceName(): string
    {
        return $this->targetClass->getNamespaceName();
    }

    /**
     * @return string
     */
    public function generateCode(): string
    {
        return $this->view->render('builder-target', [
            'namespace' => $this->targetClass->getNamespaceName(),
            'className' => $this->targetClass->getShortName(),
            'properties' => array_map(function (ReflectionProperty $property) {
                $typeString = implode('|', $property->getDocBlockTypeStrings());
                $type = ReflectionType::parse($typeString);

                return [
                    'varName' => $property->getName(),
                    'varType' => $this->getPhpType($type),
                    'paramType' => $typeString,
                    'methodName' => ucfirst($property->getName()),
                ];
            }, $this->properties),
            'builder' => [
                'shortName' => $this->getBuilderClassShortName(),
            ],
        ]);
    }

    /**
     * @return string
     */
    public function generateBuilderCode(): string
    {
        return $this->view->render('builder', [
            'namespace' => $this->getBuilderClassNamespaceName(),
            'className' => $this->getBuilderClassShortName(),
            'properties' => array_map(function (ReflectionProperty $property) {
                $typeString = $targetTypeString = implode('|', $property->getDocBlockTypeStrings());
                $type = $targetType = ReflectionType::parse($targetTypeString);

                if (!$targetType->allowsNull()) {
                    $typeString .= '|null';
                    $type = ReflectionType::parse($typeString);
                }

                return [
                    'varName' => $property->getName(),
                    'varType' => $this->getPhpType($type),
                    'targetVarType' => $this->getPhpType($targetType),
                    'paramType' => $typeString,
                    'targetParamType' => $targetTypeString,
                    'methodName' => ucfirst($property->getName()),
                ];
            }, $this->properties),
            'targetClass' => $this->getClassShortName(),
        ]);
    }

    public function getClassAst(): Node
    {
        $class = $this->targetClass;
        $astLocator = (new BetterReflection())->astLocator();
        $code = $this->generateCode();
        $reflector = new ClassReflector(new StringSourceLocator($code, $astLocator));
        $generatedClass = $reflector->reflect($class->getName());
        $class->removeMethod('__construct');

        foreach ($generatedClass->getMethods() as $method) {
            if (!$class->hasMethod($method->getName())) {
                $class->getAst()->stmts[] = $method->getAst();
            }
        }
        $propertyIndex = array_flip(Arrays::pull($this->properties, 'name'));
        usort($class->getAst()->stmts, function ($a, $b) use ($propertyIndex) {
            return $this->getStmtSort($a, $propertyIndex) <=> $this->getStmtSort($b, $propertyIndex);
        });

        return $class->getAst();
    }

    private function getPhpType(ReflectionTypeInterface $type): ?string
    {
        if ($type->isPrimitive() || $type->isArray() || $type->isClass()) {
            return $type->isArray() ? ($type->allowsNull() ? '?' : '').'array' : (string) $type;
        }

        return null;
    }

    public function getBuilderClassAst(): Node
    {
        $class = $this->builderClass;
        $astLocator = (new BetterReflection())->astLocator();
        $code = $this->generateBuilderCode();
        $reflector = new ClassReflector(new StringSourceLocator($code, $astLocator));
        $generatedClass = $reflector->reflect($class->getName());
        $class->removeMethod('build');

        foreach ($generatedClass->getMethods() as $method) {
            if (!$class->hasMethod($method->getName())) {
                $class->getAst()->stmts[] = $method->getAst();
            }
        }
        $propertyIndex = array_flip(Arrays::pull($this->properties, 'name'));
        usort($class->getAst()->stmts, function ($a, $b) use ($propertyIndex) {
            return $this->getStmtSort($a, $propertyIndex) <=> $this->getStmtSort($b, $propertyIndex);
        });

        return $class->getAst();
    }

    private function getStmtSort(Node $stmt, array $index): int
    {
        $sort = 0;
        if ($stmt instanceof Node\Stmt\Property) {
            $sort = self::SORT_PROPERTY;
            $name = $stmt->props[0]->name->toString();

            return $sort + ($index[$name] ?? 99);
        }

        if ($stmt instanceof Node\Stmt\ClassMethod) {
            $sort = self::SORT_METHOD;
            $methodName = $stmt->name->toString();
            if ('__construct' === $methodName) {
                return $sort;
            }
            if (preg_match('/^(get|set)(.*)/', $methodName, $matches)) {
                $name = lcfirst($matches[2]);
                if (isset($index[$name])) {
                    return $sort + 10 * ($index[$name] + 1) + ('get' === $matches[1] ? 0 : 1);
                } else {
                    return $sort * 2;
                }
            } else {
                return $sort * 2;
            }
        }

        return $sort;
    }
}
