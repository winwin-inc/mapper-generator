<?php

declare(strict_types=1);

namespace wenbinye\mapper;

use PhpParser\Node\Stmt\ClassMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class MappingMethod implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var \ReflectionMethod
     */
    private $method;

    /**
     * @var \ReflectionClass
     */
    private $sourceClass;

    /**
     * @var \ReflectionClass
     */
    private $targetClass;

    /**
     * @var bool
     */
    private $updateTarget;

    /**
     * MappingMethod constructor.
     *
     * @param Mapper            $mapper
     * @param \ReflectionMethod $method
     * @param \ReflectionClass  $sourceClass
     * @param \ReflectionClass  $targetClass
     * @param bool              $updateTarget
     */
    public function __construct(Mapper $mapper, \ReflectionMethod $method, string $sourceClass, string $targetClass, bool $updateTarget)
    {
        $this->mapper = $mapper;
        $this->method = $method;
        $this->sourceClass = new \ReflectionClass($sourceClass);
        $this->targetClass = new \ReflectionClass($targetClass);
        $this->updateTarget = $updateTarget;
    }

    public function generate(ClassMethod $originMethod): ClassMethod
    {
    }
}
