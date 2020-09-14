<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\annotations\InheritInverseConfiguration;
use winwin\mapper\annotations\Mapper;
use winwin\mapper\MapperTrait;

/**
 * @Mapper()
 */
class OrderItemMapper
{
    use MapperTrait;

    public function toOrder(OrderItemDto $orderItemDto): OrderItem
    {
    }

    /**
     * @InheritInverseConfiguration()
     */
    public function fromOrder(OrderItem $orderItem): OrderItemDto
    {
    }
}
