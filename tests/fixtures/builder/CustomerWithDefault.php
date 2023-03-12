<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

use winwin\mapper\attribute\Builder;

#[Builder]
class Customer
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string|null
     */
    private $name = 'john';
}
