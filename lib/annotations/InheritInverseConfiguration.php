<?php

declare(strict_types=1);

namespace winwin\mapper\annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class InheritInverseConfiguration
{
    /**
     * @var string
     * @Required()
     */
    public $value;
}
