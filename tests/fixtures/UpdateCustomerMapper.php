<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\annotations\Mapper;
use winwin\mapper\annotations\MappingTarget;

/**
 * @Mapper()
 */
class UpdateCustomerMapper
{
    /**
     * @MappingTarget("customer")
     */
    public function updateCustomer(CustomerDto $dto, Customer $customer): void
    {
    }
}
