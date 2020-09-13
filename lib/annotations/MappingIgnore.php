<?php

declare(strict_types=1);

namespace wenbinye\mapper\annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class MappingIgnore
{
    /**
     * @var array
     */
    public $value;
}
