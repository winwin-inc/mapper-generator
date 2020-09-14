<?php

declare(strict_types=1);

namespace winwin\mapper\annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Mapping
{
    /**
     * @var string
     */
    public $source;

    /**
     * @Required()
     *
     * @var string
     */
    public $target;

    /**
     * @var string
     */
    public $dateFormat;

    /**
     * @var string
     */
    public $numberFormat;

    /**
     * @var array
     */
    public $constant;

    /**
     * @var mixed
     */
    public $defaultValue;

    /**
     * @var string
     */
    public $expression;

    /**
     * @var string
     */
    public $condition;

    /**
     * @var string
     */
    public $qualifiedByName;
}
