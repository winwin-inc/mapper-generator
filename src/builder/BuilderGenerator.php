<?php

declare(strict_types=1);

namespace winwin\mapper\builder;

use Composer\Autoload\ClassLoader;
use kuiper\reflection\ReflectionFileFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use winwin\mapper\attribute\Builder;
use winwin\mapper\NamespaceNormalizer;

class BuilderGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private readonly Parser $parser;

    private readonly Standard $printer;

    /**
     * MapperGenerator constructor.
     */
    public function __construct(
        private readonly ?ClassLoader $classLoader = null,
        private readonly int $minPropertyNum = 3)
    {
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->createForVersion(PhpVersion::getHostVersion());
        $this->printer = new Standard();
    }

    public function generate(string $file): ?BuilderResult
    {
        $code = file_get_contents($file);
        if (!str_contains($code, Builder::class)) {
            return null;
        }

        $targetClass = null;
        foreach (ReflectionFileFactory::getInstance()->create($file)->getClasses() as $class) {
            $reflectionClass = new \ReflectionClass($class);
            $builderAttributes = $reflectionClass->getAttributes(Builder::class);
            if (count($builderAttributes) > 0) {
                $targetClass = $reflectionClass;
                break;
            }
        }
        if (null === $targetClass) {
            return null;
        }

        $builderTarget = BuilderTarget::create($this->parser, $targetClass);
        if (count($builderTarget->getProperties()) <= $this->minPropertyNum) {
            return null;
        }
        $result = new BuilderResult($file);
        $result->setBuilderFile(preg_replace('/\.php$/', 'Builder.php', $file));
        $this->generateTarget($builderTarget, $result);
        $this->generateBuilder($builderTarget, $result);

        return $result;
    }

    private function generateTarget(BuilderTarget $builderTarget, BuilderResult $result): void
    {
        $stmts = $this->parser->parse(file_get_contents($result->getTargetFile()));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var BuilderTarget */
            public $target;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->target->getClassShortName()) {
                    return $this->target->getClassAst($node);
                }

                return null;
            }
        };
        $visitor->target = $builderTarget;
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->addVisitor(new NamespaceNormalizer());

        $result->setTargetCode($this->printer->prettyPrintFile($traverser->traverse($stmts)));
    }

    private function generateBuilder(BuilderTarget $builderTarget, BuilderResult $result): void
    {
        if (!file_exists($result->getBuilderFile())) {
            $result->setBuilderCode($builderTarget->generateBuilderCode());

            return;
        }
        $builderTarget->setBuilderClass(new \ReflectionClass($builderTarget->getBuilderClassName()));

        $stmts = $this->parser->parse(file_get_contents($result->getBuilderFile()));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var BuilderTarget */
            public $target;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->target->getBuilderClassShortName()) {
                    return $this->target->getBuilderClassAst($node);
                }

                return null;
            }
        };
        $visitor->target = $builderTarget;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->addVisitor(new NamespaceNormalizer());
        $result->setBuilderCode($this->printer->prettyPrintFile($traverser->traverse($stmts)));
    }
}
