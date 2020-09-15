<?php

declare (strict_types=1);
namespace winwin\mapper\fixtures;

use winwin\mapper\annotations\Mapper;
use winwin\mapper\annotations\Mapping;
use winwin\mapper\annotations\MappingIgnore;
use winwin\mapper\annotations\MappingTarget;
/**
 * @Mapper()
 */
class UpdateCustomerMapper
{
    /**
     * @Mapping(source="customerName", target="name")
     * @MappingTarget("customer")
     * @MappingIgnore({"orders"})
     */
    public function updateCustomer(CustomerDto $dto, Customer $customer) : void
    {
        $customer->setId($dto->id);
        $customer->setName($dto->customerName);
    }
}