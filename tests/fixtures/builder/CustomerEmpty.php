<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

use winwin\mapper\annotations\Builder;

/**
 * @Builder()
 */
class Customer
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string|null
     */
    private $name;
}
