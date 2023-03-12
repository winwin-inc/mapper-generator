<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\attribute\Mapper;
use winwin\mapper\attribute\Mapping;
use winwin\mapper\attribute\MappingIgnore;
use winwin\mapper\attribute\MappingTarget;

#[Mapper]
class UpdateCustomerMapper
{
    #[Mapping(target: "name", source: "customerName")]
    #[MappingTarget("customer")]
    #[MappingIgnore(["orders"])]
    public function updateCustomer(CustomerDto $dto, Customer $customer): void
    {
    }
}
