<?php

declare(strict_types=1);

namespace wenbinye\mapper\annotations;

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
