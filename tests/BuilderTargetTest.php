<?php

declare(strict_types=1);

namespace winwin\mapper;

use PHPUnit\Framework\TestCase;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use winwin\mapper\fixtures\builder\Customer;

class BuilderTargetTest extends TestCase
{
    private $generator;

    protected function setUp()
    {
    }

    public function testGenerateCode()
    {
        $astLocator = (new BetterReflection())->astLocator();
        $file = __DIR__.'/fixtures/builder/Customer.php';
        $reflector = new ClassReflector(new SingleFileSourceLocator($file, $astLocator));
        $class = $reflector->reflect(Customer::class);

        $builderTarget = BuilderTarget::create(AnnotationReader::getInstance(), $class);
        $this->assertEquals($builderTarget->generateCode(), file_get_contents(__DIR__.'/fixtures/builder/Customer.php.inc'));
        $this->assertEquals($builderTarget->generateBuilderCode(), file_get_contents(__DIR__.'/fixtures/builder/CustomerBuilder.php'));
    }

    public function testDefaultValue()
    {
        $file = __DIR__.'/fixtures/builder/CustomerWithDefault.php';
        $generator = new BuilderGenerator(AnnotationReader::getInstance(), null, 1);
        $result = $generator->generate($file);
        print_r($result);
    }

    public function testDefaultValueIsConst()
    {
        $classLoader = require __DIR__.'/../vendor/autoload.php';
        $file = __DIR__.'/fixtures/builder/CustomerWithConst.php';
        $generator = new BuilderGenerator(AnnotationReader::getInstance(), $classLoader, 1);
        $result = $generator->generate($file);
        print_r($result);
    }

    public function testMinNum()
    {
        $file = __DIR__.'/fixtures/builder/Customer.php';
        $generator = new BuilderGenerator(AnnotationReader::getInstance());
        $result = $generator->generate($file);
        $this->assertNull($result);
    }

    public function testMinNumChanged()
    {
        $file = __DIR__.'/fixtures/builder/Customer.php';
        $generator = new BuilderGenerator(AnnotationReader::getInstance(), null, 1);
        $result = $generator->generate($file);
        $this->assertNotNull($result);
    }
}
