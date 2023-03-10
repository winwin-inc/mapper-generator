<?php

declare(strict_types=1);

namespace winwin\mapper;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\Reader;
use kuiper\reflection\ReflectionFileFactory;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;
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
     * @var ClassLoader|null
     */
    private $classLoader;

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
    public function __construct(Reader $annotationReader, ClassLoader $classLoader = null, int $minPropertyNum = 3)
    {
        $this->annotationReader = $annotationReader;
        $this->classLoader = $classLoader;
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

        $targetClass = null;
        foreach (ReflectionFileFactory::getInstance()->create($file)->getClasses() as $class) {
            $builder = $this->annotationReader->getClassAnnotation(new \ReflectionClass($class), Builder::class);
            if (null !== $builder) {
                $targetClass = $class;
                break;
            }
        }
        if (null === $targetClass) {
            return null;
        }

        $reflector = $this->createReflector($file);
        $builderTarget = BuilderTarget::create($this->annotationReader, $reflector->reflect($targetClass));
        if (count($builderTarget->getProperties()) <= $this->minPropertyNum) {
            return null;
        }
        $result = new BuilderResult($file);
        $result->setBuilderFile(preg_replace('/\.php$/', 'Builder.php', $file));
        $this->generateTarget($builderTarget, $result);

        // Ast 已经被上面过程修改过，可能导致后续处理错误，需要重新生成 ast
        $builderTarget = BuilderTarget::create($this->annotationReader, $reflector->reflect($targetClass));
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
                    return $this->target->getClassAst();
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
        $reflector = $this->createReflector($result->getBuilderFile());
        $builderTarget->setBuilderClass($reflector->reflect($builderTarget->getBuilderClassName()));

        $stmts = $this->parser->parse(file_get_contents($result->getBuilderFile()));
        $visitor = new class() extends NodeVisitorAbstract {
            /** @var BuilderTarget */
            public $target;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    && $node->name->toString() === $this->target->getBuilderClassShortName()) {
                    return $this->target->getBuilderClassAst();
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

    private function createReflector(string $file): ClassReflector
    {
        $astLocator = (new BetterReflection())->astLocator();
        if (null !== $this->classLoader) {
            $reflector = new ClassReflector(new ComposerSourceLocator($this->classLoader, $astLocator));
        } else {
            $reflector = new ClassReflector(new SingleFileSourceLocator($file, $astLocator));
        }

        return $reflector;
    }
}
