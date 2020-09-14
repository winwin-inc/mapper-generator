<?php

declare(strict_types=1);

namespace winwin\mapper\annotations;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class InheritConfiguration
{
    /**
     * @var string
     */
    public $value;
}
