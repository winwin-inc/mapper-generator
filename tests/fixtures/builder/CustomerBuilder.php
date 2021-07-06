<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

class CustomerBuilder
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     *
     * @return CustomerBuilder
     */
    public function setId(?int $id): CustomerBuilder
    {
        $this->id = $id;

        return $this;
    }
}
