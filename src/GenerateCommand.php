<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('generate');
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

        $generator = new MapperGenerator(AnnotationReader::getInstance());
        $generator->setLogger(new ConsoleLogger($output));
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
                $generator->replaceInFile($file);
            }
        }

        return 0;
    }
}
