<?php

declare(strict_types=1);

namespace winwin\mapper\annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Builder
{
    /**
     * 忽略属性列表.
     *
     * @var string[]
     */
    public $ignore;
}
