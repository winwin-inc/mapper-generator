<?php

declare(strict_types=1);

namespace winwin\mapper\builder;

use kuiper\helper\Arrays;
use kuiper\reflection\ReflectionDocBlockFactory;
use kuiper\reflection\ReflectionType;
use kuiper\reflection\ReflectionTypeInterface;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PHPStan\BetterReflection\SourceLocator\Ast\Exception\ParseToAstFailure;
use winwin\mapper\attribute\Builder;
use winwin\mapper\attribute\BuilderIgnore;
use winwin\mapper\TemplateEngine;

class BuilderTarget
{
    private const SORT_PROPERTY = 100;
    private const SORT_METHOD = 1000;
    private ?\ReflectionClass $builderClass = null;

    /**
     * @param \ReflectionClass      $targetClass
     * @param \ReflectionProperty[] $properties
     * @param TemplateEngine        $view
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly \ReflectionClass $targetClass,
        private readonly array $properties,
        private readonly TemplateEngine $view)
    {
    }

    public static function create(Parser $parser, \ReflectionClass $class): self
    {
        $builderAttribute = $class->getAttributes(Builder::class)[0]->newInstance();
        $properties = [];
        foreach ($class->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            if (!empty($builderAttribute->ignore) && in_array($prop->getName(), $builderAttribute->ignore, true)) {
                continue;
            }
            if (count($prop->getAttributes(BuilderIgnore::class)) > 0) {
                continue;
            }

            $properties[] = $prop;
        }

        return new self(
            $parser,
            $class,
            $properties,
            new TemplateEngine(__DIR__.'/templates')
        );
    }

    /**
     * @return \ReflectionProperty[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getClassShortName(): string
    {
        return $this->targetClass->getShortName();
    }

    public function setBuilderClass(?\ReflectionClass $builderClass): void
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
            $typeString = $this->getPropertyType($property);
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
                if ($parameter->isDefaultValueAvailable()) {
                    $params[$parameter->getName()]['hasDefaultValue'] = true;
                    // if ($parameter->isDefaultValueConstant()) {
                    // $params[$parameter->getName()]['defaultValue'] = '\\' . $parameter->getDefaultValueConstantName();
                    // } else {
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
            $targetTypeString = $this->getPropertyType($property);
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

    public function getClassAst(Node\Stmt\Class_ $node): Node\Stmt\Class_
    {
        $code = $this->generateCode();

        try {
            $stmts = $this->parser->parse($code);
        } catch (ParseToAstFailure $e) {
            throw new \InvalidArgumentException("code syntax error: \n".$code, 0, $e);
        }

        return $this->mergeAst($node, $stmts);
    }

    public function getBuilderClassAst(Node\Stmt\Class_ $node): Node
    {
        $code = $this->generateBuilderCode();
        try {
            $stmts = $this->parser->parse($code);
        } catch (ParseToAstFailure $e) {
            throw new \RuntimeException('builder code syntax error: '.$code, 0, $e);
        }

        return $this->mergeAst($node, $stmts);
    }

    private function mergeAst(Node\Stmt\Class_ $node, array $stmts): Node\Stmt\Class_
    {
        $visitor = new class() extends NodeVisitorAbstract {
            public Node\Stmt\Class_ $targetNode;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->targetNode->name->toString()) {
                    $addMethods = [];
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\ClassMethod) {
                            $addMethods[$stmt->name->toString()] = $stmt;
                        }
                    }
                    foreach ($this->targetNode->stmts as $i => $stmt) {
                        if ($stmt instanceof Node\Stmt\ClassMethod
                            && isset($addMethods[$stmt->name->toString()])) {
                            $this->targetNode->stmts[$i] = $addMethods[$stmt->name->toString()];
                            unset($addMethods[$stmt->name->toString()]);
                        }
                    }
                    $this->targetNode->stmts = array_merge($this->targetNode->stmts, array_values($addMethods));
                }

                return null;
            }
        };
        $visitor->targetNode = $node;
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);
        $propertyIndex = array_flip(Arrays::pull($this->properties, 'name'));
        usort($node->stmts, function ($a, $b) use ($propertyIndex): int {
            return $this->getStmtSort($a, $propertyIndex) <=> $this->getStmtSort($b, $propertyIndex);
        });

        return $node;
    }

    private function getPhpType(ReflectionTypeInterface $type): ?string
    {
        if ($type->isPrimitive() || $type->isArray() || $type->isClass()) {
            return $type->isArray() ? ($type->allowsNull() ? '?' : '').'array' : (string) $type;
        }

        return null;
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

    private function getPropertyType(\ReflectionProperty $property): string
    {
        $reflectionDocBlockFactory = ReflectionDocBlockFactory::getInstance();
        $propertyDoc = $reflectionDocBlockFactory->createPropertyDocBlock($property);

        return (string) $propertyDoc->getType();
    }
}
