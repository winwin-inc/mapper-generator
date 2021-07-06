<?php

declare(strict_types=1);

namespace winwin\mapper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

class BuilderGeneratorTest extends TestCase
{
    /**
     * @dataProvider provideFiles
     */
    public function testBuilder(string $file)
    {
        $generator = new BuilderGenerator(AnnotationReader::getInstance());
        $generator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        [$code, $builderCode] = $generator->generate($file);
        $expectFile = $file.'.inc';
        // file_put_contents($expectFile, $code);
        $this->assertEquals(file_get_contents($expectFile), $code);
        $expectBuilderFile = str_replace('.php', 'Builder.php', $file);
    }

    public function provideFiles(): array
    {
        return [
            [__DIR__.'/fixtures/builder/Customer.php'],
        ];
    }

    public function testName()
    {
        $generator = new BuilderGenerator(AnnotationReader::getInstance());
        $generator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $result = $generator->generate(__DIR__.'/fixtures/builder/Customer.php');
        print_r($result);
    }
}
