<?php

declare(strict_types=1);

namespace winwin\mapper;

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
use winwin\mapper\converter\DateTimeStringConverter;
use winwin\mapper\converter\EnumIntConverter;
use winwin\mapper\converter\EnumStringConverter;
use winwin\mapper\converter\IntEnumConverter;
use winwin\mapper\converter\PrimitiveConverter;
use winwin\mapper\converter\StringDateTimeConverter;
use winwin\mapper\converter\StringEnumConverter;
use winwin\mapper\fixtures\OrderItem;
use winwin\mapper\fixtures\OrderItemMapper;
use winwin\mapper\fixtures\SimpleMapper;

class MapperGeneratorTest extends TestCase
{
    public function testOrderItemMapper()
    {
        $mapperGenerator = new MapperGenerator(AnnotationReader::getInstance(), $this->createValueConverter());
        $mapperGenerator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $code = $mapperGenerator->generate(__DIR__.'/fixtures/OrderItemMapper.php');
        echo $code;
        $this->assertNotEmpty($code);
    }

    public function testCustomerMapper()
    {
        $mapperGenerator = new MapperGenerator(AnnotationReader::getInstance(), $this->createValueConverter());
        $mapperGenerator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $code = $mapperGenerator->generate(__DIR__.'/fixtures/CustomerMapper.php');
        echo $code;
        $this->assertNotEmpty($code);
    }

    public function createValueConverter(): ValueConverter
    {
        $converter = new ValueConverter();
        $converter->addConverter(new PrimitiveConverter());
        $converter->addConverter(new IntEnumConverter());
        $converter->addConverter(new EnumIntConverter());
        $converter->addConverter(new StringEnumConverter());
        $converter->addConverter(new EnumStringConverter());
        $converter->addConverter(new StringDateTimeConverter());
        $converter->addConverter(new DateTimeStringConverter());

        return $converter;
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
