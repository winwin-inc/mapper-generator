<?php

declare(strict_types=1);

namespace winwin\mapper\converter;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\CastContext;

class PrimitiveConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $from->isPrimitive() && $to->isPrimitive();
    }

    public function convert(CastContext $context): string
    {
        return '('.$context->getCastType()->getName().')'.$context->getValue();
    }
}
