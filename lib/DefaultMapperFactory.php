<?php

declare(strict_types=1);

namespace winwin\mapper;

use Doctrine\Common\Annotations\Reader;
use winwin\mapper\annotations\Mapper;

class DefaultMapperFactory implements MapperFactory
{
    /**
     * @var DefaultMapperFactory
     */
    private static $INSTANCE;

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var array<string,object>
     */
    private $mappers;

    /**
     * DefaultMapperFactory constructor.
     *
     * @param Reader $annotationReader
     */
    public function __construct(Reader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    public static function getInstance(): self
    {
        if (null === self::$INSTANCE) {
            self::$INSTANCE = new self(AnnotationReader::getInstance());
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
            $mapperAnnotation = $this->annotationReader->getClassAnnotation($class, Mapper::class);
            if (null === $mapperAnnotation) {
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
