<?php

declare(strict_types=1);

namespace winwin\mapper\attribute;

use Doctrine\Common\Annotations\Annotation\Target;

#[\Attribute(\Attribute::TARGET_METHOD)]
class MappingIgnore
{
    public function __construct(public readonly array $value)
    {
    }

}
