<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use winwin\mapper\mapper\converter\DateTimeStringConverter;
use winwin\mapper\mapper\converter\EnumIntConverter;
use winwin\mapper\mapper\converter\EnumStringConverter;
use winwin\mapper\mapper\converter\IntEnumConverter;
use winwin\mapper\mapper\converter\PrimitiveConverter;
use winwin\mapper\mapper\converter\StringDateTimeConverter;
use winwin\mapper\mapper\converter\StringEnumConverter;

class GenerateMapperCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('generate:mapper');
        $this->addArgument('path', InputArgument::REQUIRED, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = getcwd();
        $autoloadFile = $projectPath.'/vendor/autoload.php';
        if (!file_exists($autoloadFile)) {
            $output->writeln('<error>vendor/autoload.php not found</error>');

            return -1;
        }
        /** @var ClassLoader $loader */
        $loader = require $autoloadFile;
        $loader->unregister();
        $loader->register(false);

        $generator = new MapperGenerator($this->createValueConverter());
        $generator->setLogger(new ConsoleLogger($output));
        if (file_exists($projectPath.'/.mapper-config')) {
            require $projectPath.'/.mapper-config';
        }
        $path = $input->getArgument('path');
        if (is_file($path)) {
            $generator->replaceInFile($path);
        } else {
            $finder = new Finder();
            $finder
                ->ignoreVCS(true)
                ->name('*.php')
                ->notPath('vendor')
                ->in($path);
            foreach ($finder as $file) {
                $output->writeln("<info>process {$file->getPathname()}</info>");
                $generator->replaceInFile($file->getPathname());
            }
        }

        return 0;
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
