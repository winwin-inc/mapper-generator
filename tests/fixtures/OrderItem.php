<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures;

class OrderItem
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var int|null
     */
    private $quantity;

    /**
     * @var \DateTime|null
     */
    private $createTime;

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
     * @return int|null
     */
    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    /**
     * @param int|null $quantity
     */
    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreateTime(): ?\DateTime
    {
        return $this->createTime;
    }
}
