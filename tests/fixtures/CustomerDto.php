<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

class CustomerDto
{
    /**
     * @var int|null
     */
    public $id;

    /**
     * @var string|null
     */
    public $customerName;

    /**
     * @var OrderItemDto[]|null
     */
    public $orderItems;
}
