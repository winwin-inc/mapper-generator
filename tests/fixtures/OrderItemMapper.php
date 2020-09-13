<?php

declare(strict_types=1);

namespace wenbinye\mapper\fixtures;

use wenbinye\mapper\annotations\InheritInverseConfiguration;
use wenbinye\mapper\annotations\Mapper;
use wenbinye\mapper\MapperTrait;

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
