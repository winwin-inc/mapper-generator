<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Doctrine\Common\Annotations\Reader;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use wenbinye\mapper\annotations\Mapper as MapperAnnotation;

class MapperVisitor extends NodeVisitorAbstract implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var Mapper|null
     */
    private $mapper;

    /**
     * @var Mapper[]
     */
    private $mappers = [];

    /**
     * MapperVisitor constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    /**
     * @return Mapper[]
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            $mapperClass = new \ReflectionClass((string) $node->name);
            $mapper = $this->annotationReader->getClassAnnotation($mapperClass, MapperAnnotation::class);
            if (null === $mapper) {
                return NodeTraverser::STOP_TRAVERSAL;
            }
            $this->mapper = new Mapper($this->annotationReader, $mapperClass);
            $this->mapper->setLogger($this->logger);
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
}
