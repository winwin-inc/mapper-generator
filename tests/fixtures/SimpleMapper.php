<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

use winwin\mapper\MapperTrait;

class SimpleMapper
{
    use MapperTrait;

    public function updateCustomer(CustomerDto $dto, Customer $customer): Customer
    {
    }
}
