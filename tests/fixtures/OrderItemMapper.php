<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\attribute\Mapper;
use winwin\mapper\MapperTrait;

#[Mapper]
class OrderItemMapper
{
    use MapperTrait;

    public function toOrder(OrderItemDto $orderItemDto): OrderItem
    {
    }

    public function fromOrder(OrderItem $orderItem): OrderItemDto
    {
    }
}
