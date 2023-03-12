<?php

declare(strict_types=1);

namespace winwin\mapper\fixtures\builder;

use winwin\mapper\attribute\Builder;

#[Builder]
class CustomerWithConst
{
    private const DEFAULT_NAME = 'john';
    /**
     * @var int
     */
    private $id;

    /**
     * @var string|null
     */
    private $name;

    /**
     * Customer constructor.
     *
     * @param int         $id
     * @param string|null $name
     */
    public function __construct(int $id, ?string $name = self::DEFAULT_NAME)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
