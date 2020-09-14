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
            && is_a($to->getName(), \DateTime::class, true)
            && 'string' === $from->getName();
    }

    public function convert(CastContext $context): string
    {
        return sprintf("\DateTime::createFromFormat(%s, %s)",
            var_export($context->getMapping()->dateFormat ?? 'Y-m-d H:i:s', true),
            $context->getValue());
    }
}
