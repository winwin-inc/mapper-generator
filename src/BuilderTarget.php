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
use Roave\BetterReflection\Reflection\ReflectionParameter;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Ast\Exception\ParseToAstFailure;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;
use winwin\mapper\annotations\Builder;
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
        /** @var Builder $builderAnnotation */
        $builderAnnotation = $annotationReader->getClassAnnotation($reflClass, Builder::class);
        $builderTarget = new self();
        $builderTarget->view = new PhpView(__DIR__.'/templates');
        $builderTarget->targetClass = $class;
        $builderTarget->properties = array_values(array_filter($class->getProperties(), static function (ReflectionProperty $prop) use ($reflClass, $annotationReader, $builderAnnotation) {
            if ($prop->isStatic()) {
                return false;
            }
            if (!empty($builderAnnotation->ignore) && in_array($prop->getName(), $builderAnnotation->ignore, true)) {
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
        $imports = [];
        $properties = [];
        $defaultValues = $this->getConstructorParameters();
        foreach ($this->properties as $property) {
            $typeString = implode('|', $property->getDocBlockTypeStrings());
            try {
                $type = ReflectionType::parse($typeString);
            } catch (\Exception $e) {
                throw new \RuntimeException($property->getDeclaringClass()->getName().'.'.$property->getName()." type $typeString");
            }
            if ($type->isClass()) {
                $parts = explode('\\', $type->getName());
                $shortName = end($parts);
                if (!class_exists($this->targetClass->getNamespaceName().'\\'.$shortName)) {
                    $imports[ltrim($type->getName(), '\\')] = true;
                    $typeString = $shortName.($type->allowsNull() ? '|null' : '');
                    $type = ReflectionType::parse($typeString);
                }
            }
            $default = $defaultValues[$property->getName()] ?? [];

            $properties[] = [
                'varName' => $property->getName(),
                'varType' => $this->getPhpType($type),
                'paramType' => $typeString,
                'methodName' => ucfirst($property->getName()),
                'hasDefaultValue' => $default['hasDefaultValue'] ?? false,
                'defaultValue' => $default['defaultValue'] ?? null,
            ];
        }

        return $this->view->render('builder-target', [
            'namespace' => $this->targetClass->getNamespaceName(),
            'className' => $this->targetClass->getShortName(),
            'imports' => array_keys($imports),
            'properties' => $properties,
            'builder' => [
                'shortName' => $this->getBuilderClassShortName(),
            ],
        ]);
    }

    public function getConstructorParameters(): array
    {
        $params = [];
        $constructor = null;
        try {
            $constructor = $this->targetClass->getConstructor();
        } catch (\Exception $e) {
        }
        if (null !== $constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                /** @var ReflectionParameter $parameter */
                if ($parameter->isDefaultValueAvailable()) {
                    $params[$parameter->getName()]['hasDefaultValue'] = true;
                    // if ($parameter->isDefaultValueConstant()) {
                    //$params[$parameter->getName()]['defaultValue'] = '\\' . $parameter->getDefaultValueConstantName();
                    //} else {
                    $params[$parameter->getName()]['defaultValue'] = json_encode($parameter->getDefaultValue());
                    // }
                }
            }
        }
        foreach ($this->properties as $property) {
            $defaultValue = $property->getDefaultValue();
            if (null !== $defaultValue) {
                $params[$property->getName()]['hasDefaultValue'] = true;
                $params[$property->getName()]['defaultValue'] = json_encode($defaultValue);
            }
        }

        return $params;
    }

    /**
     * @return string
     */
    public function generateBuilderCode(): string
    {
        $imports = [];
        $properties = [];
        $defaultValues = $this->getConstructorParameters();

        foreach ($this->properties as $property) {
            $targetTypeString = implode('|', $property->getDocBlockTypeStrings());
            $targetType = ReflectionType::parse($targetTypeString);

            if ($targetType->isClass()) {
                $parts = explode('\\', $targetType->getName());
                $shortName = end($parts);
                if (!class_exists($this->targetClass->getNamespaceName().'\\'.$shortName)) {
                    $imports[ltrim($targetType->getName(), '\\')] = true;
                    $targetTypeString = $shortName.($targetType->allowsNull() ? '|null' : '');
                    $targetType = ReflectionType::parse($targetTypeString);
                }
            }
            $typeString = $targetTypeString;
            $type = $targetType;

            if (!$type->allowsNull()) {
                $typeString .= '|null';
                $type = ReflectionType::parse($typeString);
            }

            $default = $defaultValues[$property->getName()] ?? [];
            $properties[] = [
                'varName' => $property->getName(),
                'varType' => $this->getPhpType($type),
                'paramType' => $typeString,
                'targetVarType' => $this->getPhpType($targetType),
                'targetParamType' => $targetTypeString,
                'methodName' => ucfirst($property->getName()),
                'hasDefaultValue' => $default['hasDefaultValue'] ?? false,
                'defaultValue' => $default['defaultValue'] ?? null,
            ];
        }

        return $this->view->render('builder', [
            'namespace' => $this->getBuilderClassNamespaceName(),
            'className' => $this->getBuilderClassShortName(),
            'imports' => array_keys($imports),
            'properties' => $properties,
            'targetClass' => $this->getClassShortName(),
        ]);
    }

    public function getClassAst(): Node
    {
        $class = $this->targetClass;
        $astLocator = (new BetterReflection())->astLocator();
        $code = $this->generateCode();
        $reflector = new ClassReflector(new StringSourceLocator($code, $astLocator));
        try {
            $generatedClass = $reflector->reflect($class->getName());
        } catch (ParseToAstFailure $e) {
            throw new \InvalidArgumentException("code syntax error: \n".$code, 0, $e);
        }
        $propertyIndex = array_flip(Arrays::pull($this->properties, 'name'));

        foreach ($generatedClass->getMethods() as $method) {
            $class->removeMethod($method->getName());
            $class->getAst()->stmts[] = $method->getAst();
        }
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
        try {
            $generatedClass = $reflector->reflect($class->getName());
        } catch (ParseToAstFailure $e) {
            throw new \RuntimeException('builder code syntax error: '.$code, 0, $e);
        }
        foreach ($generatedClass->getProperties() as $property) {
            if (!$class->hasProperty($property->getName())) {
                $class->getAst()->stmts[] = $property->getAst();
            }
        }

        foreach ($generatedClass->getMethods() as $method) {
            $class->removeMethod($method->getName());
            $class->getAst()->stmts[] = $method->getAst();
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
