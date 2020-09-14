<?php

declare(strict_types=1);

namespace winwin\mapper\converter;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\CastContext;

class StringDateTimeConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $to->isClass()
            && is_subclass_of($to->getName(), \DateTime::class)
            && 'string' === $from->getName();
    }

    public function convert(CastContext $context): string
    {
        return "\DateTime::createFromFormat(".$context->getValue().')';
    }
}
