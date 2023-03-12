<?php

declare(strict_types=1);

namespace winwin\mapper\mapper\converter;

use kuiper\reflection\ReflectionTypeInterface;
use winwin\mapper\mapper\CastContext;

interface Converter
{
    public function support(ReflectionTypeInterface $from, ReflectionTypeInterface $to): bool;

    public function convert(CastContext $context): string;
}
