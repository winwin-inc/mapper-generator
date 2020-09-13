<?php

declare(strict_types=1);

namespace wenbinye\mapper\fixtures;

use wenbinye\mapper\annotations\Mapper;
use wenbinye\mapper\MapperTrait;

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

    public function toCustomer(CustomerDto $customerDto): Customer
    {
    }
}
