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
        $generator = new BuilderGenerator(AnnotationReader::getInstance(), 1);
        $result = $generator->generate($file);
        $this->assertNotNull($result);
    }
}
