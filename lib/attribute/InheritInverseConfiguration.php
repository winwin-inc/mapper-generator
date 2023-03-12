<?php

declare(strict_types=1);

namespace winwin\mapper\attribute;

use Doctrine\Common\Annotations\Annotation\Required;

#[\Attribute(\Attribute::TARGET_METHOD)]
class InheritInverseConfiguration
{
    public function __construct(public readonly string $value)
    {
    }

}
