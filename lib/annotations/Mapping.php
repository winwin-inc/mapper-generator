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
     * an expression to test source value, can use `%s` to replace the value,
     * for example condition="ctype_upper(%s)".
     *
     * @var string
     */
    public $condition;

    /**
     * @var string
     */
    public $qualifiedByName;
}
