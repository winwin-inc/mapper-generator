<?php

declare(strict_types=1);

namespace winwin\mapper;

use winwin\mapper\converter\Converter;

class ValueConverter
{
    /**
     * @var Converter[]
     */
    private $converters;

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
                return $converter->convert($context);
            }
        }
        throw new \InvalidArgumentException(sprintf('cannot convert from %s to %s', $context->getOriginType(), $context->getCastType()));
    }
}
