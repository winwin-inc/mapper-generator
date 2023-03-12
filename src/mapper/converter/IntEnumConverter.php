<?php

declare(strict_types=1);

namespace winwin\mapper\mapper\converter;

use kuiper\helper\Enum;
use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\mapper\CastContext;

class IntEnumConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $to->isClass()
            && is_subclass_of($to->getName(), Enum::class)
            && 'int' === $from->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('\\%s::fromOrdinal(%s)', $context->getCastType()->getName(), $context->getValue());
    }
}
