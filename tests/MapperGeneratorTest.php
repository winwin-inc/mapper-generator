<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Reflection\ClassReflection;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use wenbinye\mapper\fixtures\OrderItem;
use wenbinye\mapper\fixtures\OrderItemMapper;
use wenbinye\mapper\fixtures\SimpleMapper;

class MapperGeneratorTest extends TestCase
{
    public function testName()
    {
        $mapperGenerator = new MapperGenerator(AnnotationReader::getInstance());
        $mapperGenerator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $mapperGenerator->generate(__DIR__.'/fixtures/OrderItemMapper.php');
    }

    public function testscratch()
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $stmts = $parser->parse(file_get_contents(__DIR__.'/fixtures/OrderItemMapper.php'));

        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            private $methods;
            /**
             * @var \PhpParser\Parser
             */
            private $parser;

            public function setParser($parser): void
            {
                $this->parser = $parser;
            }

            public function setMethods($methods)
            {
                $this->methods = $methods;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod && 'toOrder' === (string) $node->name) {
                    return $this->methods[(string) $node->name];
                }

                return null;
            }
        };
        $visitor->setParser($parser);
        $visitor->setMethods($this->generateMethods());
        $nodeTraverser->addVisitor($visitor);
        $stmts = $nodeTraverser->traverse($stmts);

        $printer = new Standard();
        echo $printer->prettyPrintFile($stmts);
    }

    private function generateMethods()
    {
        $class = ClassGenerator::fromReflection(new ClassReflection(OrderItemMapper::class));
        $class->removeMethod('getInstance');
        $method = $class->getMethod('toOrder');
        $method->setBody('return new \\'.OrderItem::class.'();');

        $file = new FileGenerator();
        $file->setClass($class);

        $parserFactory = new ParserFactory();
        $parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $code = $file->generate();
        $stmts = $parser->parse($code);
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            public $methods;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->methods[(string) $node->name] = $node;
                }
            }
        };
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($stmts);

        return $visitor->methods;
    }

    public function testCodeGen()
    {
        $class = new \ReflectionClass(SimpleMapper::class);
        $method = $class->getMethod('updateCustomer');
        $type = $method->getReturnType();
        echo $method.' return type '.$type, "\n";
        var_export([
            $type->isBuiltin(),
            $type->getName(),
        ]);
    }
}
