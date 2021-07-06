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

    public function __construct(?Customer $value = null)
    {
        if (null !== $value) {
            $this->id = $value->getId();
            $this->name = $value->getName();
        }
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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
     *
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function build(): Customer
    {
        return new Customer($this->id, $this->name);
    }
}
