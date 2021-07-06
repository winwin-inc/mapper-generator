<?php

declare(strict_types=1);

namespace winwin\mapper;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NamespaceNormalizer extends NodeVisitorAbstract
{
    /**
     * @var string
     */
    private $namespace = '';

    /**
     * @var string[]
     */
    private $importNames = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name->toCodeString();
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Use_
                    && Node\Stmt\Use_::TYPE_NORMAL === $stmt->type) {
                    foreach ($stmt->uses as $use) {
                        $alias = null === $use->alias ? $use->name->getLast() : $use->alias->toString();
                        $this->addImport($alias, $use->name->toCodeString());
                    }
                }
            }
        }
        if ($node instanceof Node\Stmt\Class_) {
            return $this->replaceWithImport($node);
        }

        return null;
    }

    public function addImport(string $alias, string $className): void
    {
        $this->importNames[$alias] = $className;
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

    public function replaceWithImport(Node $node): Node
    {
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            public $self;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Name\FullyQualified) {
                    return $this->self->toRelativeName($node);
                }

                return null;
            }
        };
        $visitor->self = $this;
        $nodeTraverser->addVisitor($visitor);

        return $nodeTraverser->traverse([$node])[0];
    }
}
