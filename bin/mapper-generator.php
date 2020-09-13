#!/usr/bin/env php
<?php

use Symfony\Component\Console\Application;
use wenbinye\mapper\GenerateCommand;

foreach ([__DIR__.'/../../../autoload.php', __DIR__.'/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        $autoload = $file;
        break;
    }
}
if (!isset($autoload)) {
    fwrite(STDERR,
        'You need to set up the project dependencies using the following commands:'.PHP_EOL.
        'wget http://getcomposer.org/composer.phar'.PHP_EOL.
        'php composer.phar install'.PHP_EOL
    );

    die(1);
}
require $autoload;
unset($autoload);

$app = new Application('Mapper Generator', '@git-version@');

$app->add(new GenerateCommand());
$app->setDefaultCommand('generate', true);
$app->run();
