<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

use winwin\mapper\attribute\Builder;

#[Builder]
class Customer
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string|null
     */
    private $name;

    private ?string $result = null;

    /**
     * Customer constructor.
     *
     * @param int         $id
     * @param string|null $name
     */
    public function __construct(int $id, ?string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * @param string|null $result
     */
    public function setResult(?string $result): void
    {
        $this->result = $result;
    }
}
