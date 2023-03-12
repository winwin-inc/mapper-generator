<?php

declare(strict_types=1);

namespace winwin\mapper;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use winwin\mapper\mapper\converter\DateTimeStringConverter;
use winwin\mapper\mapper\converter\EnumIntConverter;
use winwin\mapper\mapper\converter\EnumStringConverter;
use winwin\mapper\mapper\converter\IntEnumConverter;
use winwin\mapper\mapper\converter\PrimitiveConverter;
use winwin\mapper\mapper\converter\StringDateTimeConverter;
use winwin\mapper\mapper\converter\StringEnumConverter;
use winwin\mapper\fixtures\UpdateCustomerMapper;
use winwin\mapper\mapper\MapperGenerator;
use winwin\mapper\mapper\ValueConverter;

class MapperGeneratorTest extends TestCase
{
    /**
     * @dataProvider provideFiles
     */
    public function testMapper(string $file)
    {
        $mapperGenerator = new MapperGenerator($this->createValueConverter());
        $mapperGenerator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $code = $mapperGenerator->generate($file);
        $expectFile = $file.'.result';
        // file_put_contents($expectFile, $code);
        $this->assertEquals(file_get_contents($expectFile), $code);
    }

    public function testOne()
    {
        $this->testMapper(__DIR__.'/fixtures/CustomerMapper.php');
    }

    public function provideFiles(): array
    {
        return [
            [__DIR__.'/fixtures/OrderItemMapper.php'],
            [__DIR__.'/fixtures/CustomerMapper.php'],
            [__DIR__.'/fixtures/UpdateCustomerMapper.php'],
        ];
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

    public function testCodeGen()
    {
        $reflectionClass = ReflectionClass::createFromName(UpdateCustomerMapper::class);
        $reflectionClass->getMethod('updateCustomer')->setBodyFromString('
        $customer->setId($dto->id);
        $customer->setName($dto->customerName);
        ');
        $printer = new Standard();

        // echo $printer->prettyPrintFile([$reflectionClass->getDeclaringNamespaceAst()]);

        $parserFactory = new ParserFactory();
        $parser = $parserFactory->create(ParserFactory::ONLY_PHP7);
        $stmts = $parser->parse(file_get_contents(__DIR__.'/fixtures/UpdateCustomerMapper.php'));
        $nodeTraverser = new NodeTraverser();
        $visitor = new class() extends NodeVisitorAbstract {
            public $class;

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod && 'updateCustomer' === $node->name->toString()) {
                    $node->stmts = $this->class->getMethod('updateCustomer')->getBodyAst();

                    return $node;
                }

                return null;
            }
        };
        $visitor->class = $reflectionClass;
        $nodeTraverser->addVisitor($visitor);
        $result = $nodeTraverser->traverse($stmts);

//        echo $printer->prettyPrintFile($result);
        $this->assertTrue(true);
    }
}
