<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use Composer\Autoload\ClassLoader;

class ComposerLoader
{
    /**
     * @var string
     */
    private $projectPath;

    /**
     * ComposerLoader constructor.
     *
     * @param string $projectPath
     */
    public function __construct(string $projectPath)
    {
        $this->projectPath = $projectPath;
    }

    public function getLoader(): ClassLoader
    {
        $autoloadPath = $this->projectPath.'/vendor/composer';
        $loader = new ClassLoader();
        $useStaticLoader = PHP_VERSION_ID >= 50600 && !defined('HHVM_VERSION') && (!function_exists('zend_loader_file_encoded') || !zend_loader_file_encoded());
        if ($useStaticLoader) {
            require_once $autoloadPath.'/autoload_static.php';

            call_user_func(\Composer\Autoload\ComposerStaticInit349ffe8236e0db33db6e58e3e0751ff5::getInitializer($loader));
        } else {
            $map = require $autoloadPath.'/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = require $autoloadPath.'/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }

            $classMap = require $autoloadPath.'/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }
        }

        return $loader;
    }
}
