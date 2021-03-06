<?php

declare (strict_types=1);
namespace winwin\mapper\fixtures;

use winwin\mapper\annotations\Mapper;
use winwin\mapper\MapperTrait;
/**
 * @Mapper()
 */
class OrderItemMapper
{
    use MapperTrait;
    public function toOrder(OrderItemDto $orderItemDto) : OrderItem
    {
        $orderItem = new OrderItem();
        $orderItem->setName($orderItemDto->name);
        $orderItem->setQuantity($orderItemDto->quantity);
        return $orderItem;
    }
    public function fromOrder(OrderItem $orderItem) : OrderItemDto
    {
        $orderItemDto = new OrderItemDto();
        $orderItemDto->name = $orderItem->getName();
        $orderItemDto->quantity = $orderItem->getQuantity();
        $orderItemDto->createTime = $orderItem->getCreateTime() === null ? null : $orderItem->getCreateTime()->format('Y-m-d H:i:s');
        return $orderItemDto;
    }
}