<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\attribute\Mapper;
use winwin\mapper\attribute\Mapping;
use winwin\mapper\MapperTrait;

#[Mapper]
class CustomerMapper
{
    use MapperTrait;

    public function __construct(private readonly OrderItemMapper $orderItemMapper)
    {
    }

    #[Mapping(target: "name", source: "customerName")]
    #[Mapping(target: "orders", source: "orderItems", qualifiedByName: "toOrders")]
    public function toCustomer(CustomerDto $customerDto): Customer
    {
    }

    #[Mapping(target: "customerName", source: "name")]
    #[Mapping(target: "orderItems", source: "orders", qualifiedByName: "fromOrders")]
    public function fromCustomer(Customer $customer): CustomerDto
    {
    }

    public function toOrders(array $orderItemDtoList): array
    {
        $orders = [];
        foreach ($orderItemDtoList as $item) {
            $orders[] = $this->orderItemMapper->toOrder($item);
        }

        return $orders;
    }

    public function fromOrders(array $orders): array
    {
        $orderItems = [];
        foreach ($orders as $item) {
            $orderItems[] = $this->orderItemMapper->fromOrder($item);
        }

        return $orderItems;
    }
}
