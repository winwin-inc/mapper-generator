<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;

class AnnotationReader
{
    /**
     * @var Reader|null
     */
    private static $INSTANCE;

    public static function getInstance(): Reader
    {
        if (null === self::$INSTANCE) {
            AnnotationRegistry::registerLoader('class_exists');
            self::$INSTANCE = new \Doctrine\Common\Annotations\AnnotationReader();
        }

        return self::$INSTANCE;
    }
}
