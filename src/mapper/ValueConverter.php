<?php

declare(strict_types=1);

namespace winwin\mapper\mapper;

use winwin\mapper\mapper\converter\Converter;

class ValueConverter
{
    /**
     * @var Converter[]
     */
    private array $converters = [];

    public function addConverter(Converter $converter, bool $append = true): void
    {
        if ($append) {
            $this->converters[] = $converter;
        } else {
            array_unshift($this->converters, $converter);
        }
    }

    public function removeConverter(string $converterClass): void
    {
        foreach ($this->converters as $i => $converter) {
            if (is_a($converter, $converterClass, true)) {
                unset($this->converters[$i]);
            }
        }
    }

    public function convert(CastContext $context): string
    {
        foreach ($this->converters as $converter) {
            if ($converter->support($context->getOriginType(), $context->getCastType())) {
                if ($context->getSourceField()->getType()->allowsNull()) {
                    return sprintf('%s === null ? null : (%s)', $context->getValue(), $converter->convert($context));
                } else {
                    return $converter->convert($context);
                }
            }
        }
        throw new \InvalidArgumentException(sprintf('cannot convert from %s to %s', $context->getOriginType(), $context->getCastType()));
    }
}
