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
                        $this->importNames[$use->alias] = $use->name->toCodeString();
                    }
                }
            }
            break;
        }
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
            $this->mapper = new Mapper($this->annotationReader, $this->converter, $this->parser, $mapperClass);
            $this->mapper->setLogger($this->logger);
            $this->mapper->initialize();
            $this->mappers[] = $this->mapper;
        } elseif ($node instanceof Node\Stmt\ClassMethod
            && null !== $this->mapper
            && $this->mapper->hasMappingMethod((string) $node->name)) {
            return $this->mapper->generateMethod($node);
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
    }

    private function getClassName(Node $name): string
    {
        if ($name instanceof Node\Name && $name->isFullyQualified()) {
            return $name->toCodeString();
        }
        $className = (string) $name;
        if (isset($this->importNames[$className])) {
            return $this->importNames[$className];
        }

        return $this->namespace.'\\'.$className;
    }
}
