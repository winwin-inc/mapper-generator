<?php

declare(strict_types=1);

namespace winwin\mapper;

use winwin\mapper\attribute\Mapper;

class DefaultMapperFactory implements MapperFactory
{
    private static ?DefaultMapperFactory $INSTANCE = null;

    /**
     * @var array<string,object>
     */
    private array $mappers = [];


    public static function getInstance(): self
    {
        if (null === self::$INSTANCE) {
            self::$INSTANCE = new self();
        }

        return self::$INSTANCE;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapper(string $mapperClass)
    {
        if (!isset($this->mappers[$mapperClass])) {
            $class = new \ReflectionClass($mapperClass);
            $mapperAttributes = $class->getAttributes(Mapper::class);
            if (count($mapperAttributes) === 0) {
                throw new \InvalidArgumentException("Class $mapperClass not annotated with ".Mapper::class);
            }
            $args = [];
            if (null !== $class->getConstructor()) {
                foreach ($class->getConstructor()->getParameters() as $i => $parameter) {
                    if (null !== $parameter->getType() && class_exists($parameter->getType()->getName())) {
                        $args[] = $this->getMapper($parameter->getType()->getName());
                    } else {
                        throw new \InvalidArgumentException("Class $mapperClass {$i}th constructor parameter should be an mapper");
                    }
                }
            }
            $this->mappers[$mapperClass] = $class->newInstanceArgs($args);
        }

        return $this->mappers[$mapperClass];
    }
}
