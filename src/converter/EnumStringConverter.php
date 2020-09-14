<?php

declare(strict_types=1);

namespace winwin\mapper\converter;

use kuiper\helper\Enum;
use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\CastContext;

class EnumStringConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $from->isClass()
            && is_subclass_of($from->getName(), Enum::class)
            && 'string' === $to->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('%s->name', $context->getValue());
    }
}
