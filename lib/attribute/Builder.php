<?php

declare(strict_types=1);

namespace winwin\mapper\attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Builder
{
    /**
     * @param string[] $ignore 忽略属性列表
     */
    public function __construct(public readonly array $ignore = [])
    {
    }

}
