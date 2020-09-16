<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\Reader;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\annotations\Mapper as MapperAnnotation;

class MapperVisitor extends NodeVisitorAbstract implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
     * @var Mapper|null
     */
    private $mapper;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var array<string,string>
     */
    private $importNames;

    /**
     * @var Mapper[]
     */
    private $mappers = [];

    /**
     * MapperVisitor constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader, ValueConverter $converter, Parser $parser)
    {
        $this->annotationReader = $annotationReader;
        $this->converter = $converter;
        $this->parser = $parser;
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
     * @return Mapper[]
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    public function beforeTraverse(array $nodes)
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Node\Stmt\Namespace_) {
                continue;
            }
            $this->namespace = $node->name->toCodeString();
            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof Node\Stmt\Use_ || Node\Stmt\Use_::TYPE_NORMAL !== $stmt->type) {
                    continue;
                }
                foreach ($stmt->uses as $use) {
                    if (null === $use->alias) {
                        $this->importNames[$use->name->getLast()] = $use->name->toCodeString();
                    } else {
                        $this->importNames[$use->alias->toString()] = $use->name->toCodeString();
                    }
                }
            }
            break;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $mapperClass = new \ReflectionClass($this->getClassName($node->name));
            $mapper = $this->annotationReader->getClassAnnotation($mapperClass, MapperAnnotation::class);
            if (null === $mapper) {
                return NodeTraverser::STOP_TRAVERSAL;
            }
            $this->mapper = new Mapper($this, $mapperClass);
            $this->mapper->setLogger($this->logger);
            $this->mapper->initialize();
            $this->mappers[] = $this->mapper;
        } elseif ($node instanceof Node\Stmt\ClassMethod
            && null !== $this->mapper
            && $this->mapper->hasMappingMethod($node->name->toString())) {
            $node->stmts = $this->mapper->getBodyAst($node->name->toString());

            return $this->replaceWithImport($node);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->mapper = null;
        }

        return null;
    }

    /**
     * @param Node\Identifier|Node\Name $name
     *
     * @return string
     */
    private function getClassName($name): string
    {
        if ($name instanceof Node\Name && $name->isFullyQualified()) {
            return $name->toCodeString();
        }
        $className = $name->toString();

        return $this->importNames[$className] ?? ($this->namespace.'\\'.$className);
    }

    public function getClassAlias(string $className): ?string
    {
        $key = array_search(ltrim($className, '\\'), $this->importNames, true);
        if (false !== $key) {
            return $key;
        }

        return null;
    }

    public function toRelativeName(Node\Name\FullyQualified $node): Node\Name
    {
        $key = array_search(ltrim($node->toCodeString(), '\\'), $this->importNames, true);
        if (false !== $key) {
            return new Node\Name($key, $node->getAttributes());
        }
        $namespace = $node->slice(0, -1);
        if (null !== $namespace && ltrim($namespace->toCodeString(), '\\') === $this->namespace) {
            return new Node\Name($node->getLast(), $node->getAttributes());
        }

        return $node;
    }

    private function replaceWithImport(Node\Stmt\ClassMethod $stmts): Node
    {
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            /**
             * @var MapperVisitor
             */
            public $mapper;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Name\FullyQualified) {
                    return $this->mapper->toRelativeName($node);
                }

                return null;
            }
        };
        $visitor->mapper = $this;
        $nodeTraverser->addVisitor($visitor);

        return $nodeTraverser->traverse([$stmts])[0];
    }
}
