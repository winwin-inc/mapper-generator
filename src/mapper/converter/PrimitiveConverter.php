<?php

declare(strict_types=1);

namespace winwin\mapper\mapper\converter;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\mapper\CastContext;

class PrimitiveConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $from->isPrimitive() && $to->isPrimitive();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('(%s) %s', $context->getCastType()->getName(), $context->getValue());
    }
}
