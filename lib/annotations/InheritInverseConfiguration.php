<?php

declare(strict_types=1);

namespace winwin\mapper\annotations;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class InheritInverseConfiguration
{
    /**
     * @var string
     */
    public $value;
}
