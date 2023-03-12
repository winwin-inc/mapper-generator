<?php

declare(strict_types=1);

namespace winwin\mapper;

class TemplateEngine
{
    private readonly string $baseDir;

    public function __construct(string $baseDir, private readonly string $extension = '.php')
    {
        $this->baseDir = rtrim($baseDir, '/');
    }

    public function render(string $name, array $context = []): string
    {
        extract($context, EXTR_SKIP);
        ob_start();
        include $this->baseDir.'/'.$name.$this->extension;

        return ob_get_clean();
    }
}
