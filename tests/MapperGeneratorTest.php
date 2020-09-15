<?php

declare(strict_types=1);

namespace winwin\mapper;

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

class MapperGeneratorTest extends TestCase
{
    /**
     * @dataProvider provideFiles
     */
    public function testOrderItemMapper(string $file)
    {
        $mapperGenerator = new MapperGenerator(AnnotationReader::getInstance(), $this->createValueConverter());
        $mapperGenerator->setLogger(new ConsoleLogger(new ConsoleOutput()));
        $code = $mapperGenerator->generate($file);
        $expectFile = $file.'.inc';
        // file_put_contents($expectFile, $code);
        $this->assertEquals(file_get_contents($expectFile), $code);
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
}
