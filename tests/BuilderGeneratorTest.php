<?php

declare(strict_types=1);

namespace winwin\mapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use winwin\mapper\builder\BuilderGenerator;

class BuilderGeneratorTest extends TestCase
{
    /**
     * @dataProvider provideFiles
     */
    public function testBuilder(string $file)
    {
        $generator = new BuilderGenerator(minPropertyNum: 0);
        $generator->setLogger(new ConsoleLogger(new ConsoleOutput()));
         $result = $generator->generate($file);
        $expectFile = $file.'.inc';
        // file_put_contents($expectFile, $code);
        $this->assertEquals(file_get_contents($file.'.result'), $result->getTargetCode());
        $expectBuilderFile = str_replace('.php', 'Builder.php', $file);
        $this->assertEquals(file_get_contents($expectBuilderFile), $result->getBuilderCode());
    }

    public function provideFiles(): array
    {
        return [
           // [__DIR__.'/fixtures/builder/CustomerEmpty.php'],
             [__DIR__.'/fixtures/builder/Customer.php'],
        ];
    }

    public function testName()
    {
        $generator = new BuilderGenerator(minPropertyNum: 0);
        $generator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $result = $generator->generate(__DIR__.'/fixtures/builder/Customer.php');
        print_r($result);
    }
}
