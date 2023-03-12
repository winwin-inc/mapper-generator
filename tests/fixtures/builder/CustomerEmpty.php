<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

use winwin\mapper\attribute\Builder;

#[Builder]
class CustomerEmpty
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $name)
    {
    }
}
