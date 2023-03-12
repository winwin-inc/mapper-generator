<?php

declare(strict_types=1);

namespace winwin\mapper\mapper\converter;

use kuiper\helper\Enum;
use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\mapper\CastContext;

class EnumIntConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $from->isClass()
            && is_subclass_of($from->getName(), Enum::class)
            && 'int' === $to->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('%s->ordinal()', $context->getValue());
    }
}
