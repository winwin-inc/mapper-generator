<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

class Customer
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
     * @var OrderItem[]|null
     */
    private $orders;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return OrderItem[]|null
     */
    public function getOrders(): ?array
    {
        return $this->orders;
    }

    /**
     * @param OrderItem[]|null $orders
     */
    public function setOrders(?array $orders): void
    {
        $this->orders = $orders;
    }
}
