<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\Reader;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use winwin\mapper\annotations\Builder;

class BuilderGenerator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var \PhpParser\Parser
     */
    private $parser;

    /**
     * @var Standard
     */
    private $printer;

    /**
     * @var int
     */
    private $minPropertyNum;

    /**
     * MapperGenerator constructor.
     */
    public function __construct(Reader $annotationReader, int $minPropertyNum = 3)
    {
        $this->annotationReader = $annotationReader;
        $this->minPropertyNum = $minPropertyNum;
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard();
    }

    public function generate(string $file): ?BuilderResult
    {
        $code = file_get_contents($file);
        if (false === strpos($code, Builder::class)) {
            return null;
        }

        $astLocator = (new BetterReflection())->astLocator();
        $reflector = new ClassReflector(new SingleFileSourceLocator($file, $astLocator));
        $builderTarget = null;
        foreach ($reflector->getAllClasses() as $class) {
            $builder = $this->annotationReader->getClassAnnotation(new \ReflectionClass($class->getName()), Builder::class);
            if (null !== $builder) {
                $builderTarget = BuilderTarget::create($class);
                break;
            }
        }
        if (null === $builderTarget || count($builderTarget->getProperties()) <= $this->minPropertyNum) {
            return null;
        }
        $result = new BuilderResult($file);
        $result->setBuilderFile(preg_replace('/\.php$/', 'Builder.php', $file));
        $this->generateBuildTarget($builderTarget, $result);
        $this->generateBuilder($builderTarget, $result);

        return $result;
    }

    private function generateBuildTarget(BuilderTarget $builderTarget, BuilderResult $result): void
    {
        $stmts = $this->parser->parse(file_get_contents($result->getTargetFile()));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var BuilderTarget */
            public $target;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Namespace_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\Use_
                            && Node\Stmt\Use_::TYPE_NORMAL === $stmt->type) {
                            foreach ($stmt->uses as $use) {
                                $alias = null === $use->alias ? $use->name->getLast() : $use->alias->toString();
                                $this->target->addImport($alias, $use->name->toCodeString());
                            }
                        }
                    }
                }
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->target->getClassShortName()) {
                    return $this->target->replaceWithImport($this->target->getClassAst());
                }

                return null;
            }
        };
        $visitor->target = $builderTarget;
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);

        $result->setTargetCode($this->printer->prettyPrintFile($traverser->traverse($stmts)));
    }

    private function generateBuilder(BuilderTarget $builderTarget, BuilderResult $result): void
    {
        if (!file_exists($result->getBuilderFile())) {
            $result->setBuilderCode($builderTarget->generateBuilderCode());

            return;
        }
        $astLocator = (new BetterReflection())->astLocator();
        $reflector = new ClassReflector(new SingleFileSourceLocator($result->getBuilderFile(), $astLocator));
        $builderTarget->setBuilderClass($reflector->reflect($builderTarget->getBuilderClassName()));

        $stmts = $this->parser->parse(file_get_contents($result->getBuilderFile()));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var BuilderTarget */
            public $target;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->target->getBuilderClassShortName()) {
                    return $this->target->replaceWithImport($this->target->getBuilderClassAst());
                }

                return null;
            }
        };
        $visitor->target = $builderTarget;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $result->setBuilderCode($this->printer->prettyPrintFile($traverser->traverse($stmts)));
    }
}
