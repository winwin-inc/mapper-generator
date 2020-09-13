<?php

declare(strict_types=1);

namespace wenbinye\mapper;

trait MapperTrait
{
    public static function getInstance(): self
    {
        return DefaultMapperFactory::getInstance()->getMapper(static::class);
    }
}
