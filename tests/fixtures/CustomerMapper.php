<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\annotations\Mapper;
use winwin\mapper\annotations\Mapping;
use winwin\mapper\MapperTrait;

/**
 * @Mapper()
 */
class CustomerMapper
{
    use MapperTrait;

    /**
     * @var OrderItemMapper
     */
    private $orderItemMapper;

    /**
     * CustomerMapper constructor.
     *
     * @param OrderItemMapper $orderItemMapper
     */
    public function __construct(OrderItemMapper $orderItemMapper)
    {
        $this->orderItemMapper = $orderItemMapper;
    }

    /**
     * @Mapping(source="customerName", target="name")
     * @Mapping(source="orderItems", target="orders", qualifiedByName="toOrders")
     */
    public function toCustomer(CustomerDto $customerDto): Customer
    {
    }

    /**
     * @Mapping(source="name", target="customerName")
     * @Mapping(source="orders", target="orderItems", qualifiedByName="fromOrders")
     */
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
