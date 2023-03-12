<?php

declare(strict_types=1);

namespace winwin\mapper\attribute;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Mapping
{
    /**
     * @param string $target
     * @param string|null $source
     * @param string|null $dateFormat
     * @param string|null $numberFormat
     * @param array|null $constant
     * @param mixed $defaultValue
     * @param string|null $expression
     * @param string|null $condition an expression to test source value, can use `%s` to replace the value,
     * for example condition="ctype_upper(%s)".
     * @param string|null $qualifiedByName
     */
    public function __construct(
        public readonly string  $target,
        public readonly ?string $source = null,
        public readonly ?string $dateFormat = null,
        public readonly ?string $numberFormat = null,
        public readonly ?array  $constant = null,
        public readonly mixed   $defaultValue = null,
        public readonly ?string $expression = null,
        public readonly ?string $condition = null,
        public readonly ?string $qualifiedByName = null)
    {
    }


}
