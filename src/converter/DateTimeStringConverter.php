<?php

declare(strict_types=1);

namespace winwin\mapper\converter;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\CastContext;

class DateTimeStringConverter implements Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool
    {
        return $from->isClass()
            && is_a($from->getName(), \DateTimeInterface::class, true)
            && 'string' === $to->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf('%s->format(%s)',
            $context->getValue(),
            var_export($context->getMapping()->dateFormat ?? 'Y-m-d H:i:s', true));
    }
}
