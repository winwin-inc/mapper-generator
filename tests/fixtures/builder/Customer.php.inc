<?php
namespace winwin\mapper\fixtures\builder;

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

    /**
     * @param int  $id
     * @param string|null  $name
     */
    public function __construct(int $id, ?string $name)
    {
         $this->id = $id;
         $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId() :int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getName() :?string
    {
        return $this->name;
    }

    public static function builder(Customer $other = null): CustomerBuilder
    {
        return new CustomerBuilder($other);
    }
}
