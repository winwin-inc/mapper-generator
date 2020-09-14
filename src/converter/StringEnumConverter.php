<?php

declare(strict_types=1);

namespace winwin\mapper\converter;

use kuiper\helper\Enum;
use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\CastContext;

class StringEnumConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $to->isClass()
            && is_subclass_of($to->getName(), Enum::class)
            && 'string' === $from->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('%s::fromName(strtoupper(%s))',
            $context->getCastType()->getName(), $context->getValue());
    }
}
