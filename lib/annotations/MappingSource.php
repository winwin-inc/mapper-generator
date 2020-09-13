<?php

declare(strict_types=1);

namespace wenbinye\mapper\annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class MappingSource
{
    /**
     * @var string
     * @Required()
     */
    public $value;
}
