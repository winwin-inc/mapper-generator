<?php

declare (strict_types=1);
namespace winwin\mapper\fixtures\builder;

use winwin\mapper\attribute\Builder;
class CustomerEmptyBuilder
{
    public function __construct(?CustomerEmpty $value = null)
    {
        if ($value !== null) {
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
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }
    /**
     * @param ?string $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function build(): CustomerEmpty
    {
        return new CustomerEmpty($this->id, $this->name);
    }
}