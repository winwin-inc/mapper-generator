<?php

declare(strict_types=1);

namespace wenbinye\mapper\fixtures;

use wenbinye\mapper\MapperTrait;

class SimpleMapper
{
    use MapperTrait;

    public function updateCustomer(CustomerDto $dto, Customer $customer): Customer
    {
    }
}
