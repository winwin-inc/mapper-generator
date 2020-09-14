<?php

declare(strict_types=1);

namespace winwin\mapper;

interface MapperFactory
{
    /**
     * @param string $mapperClass
     *
     * @return object the mapper instance
     */
    public function getMapper(string $mapperClass);
}
