<?php

declare(strict_types=1);

namespace winwin\mapper\builder;

class BuilderResult
{
    private bool $targetChanged = true;

    private string $targetCode = '';

    private string $builderFile = '';

    private bool $builderChanged = true;

    private string $builderCode = '';

    public function __construct(private readonly string $targetFile)
    {
    }

    /**
     * @return string
     */
    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    /**
     * @return bool
     */
    public function isTargetChanged(): bool
    {
        return $this->targetChanged;
    }

    /**
     * @param bool $targetChanged
     */
    public function setTargetChanged(bool $targetChanged): void
    {
        $this->targetChanged = $targetChanged;
    }

    /**
     * @return string
     */
    public function getTargetCode(): string
    {
        return $this->targetCode;
    }

    /**
     * @param string $targetCode
     */
    public function setTargetCode(string $targetCode): void
    {
        $this->targetCode = $targetCode;
    }

    /**
     * @return string
     */
    public function getBuilderFile(): string
    {
        return $this->builderFile;
    }

    /**
     * @param string $builderFile
     */
    public function setBuilderFile(string $builderFile): void
    {
        $this->builderFile = $builderFile;
    }

    /**
     * @return bool
     */
    public function isBuilderChanged(): bool
    {
        return $this->builderChanged;
    }

    /**
     * @param bool $builderChanged
     */
    public function setBuilderChanged(bool $builderChanged): void
    {
        $this->builderChanged = $builderChanged;
    }

    /**
     * @return string
     */
    public function getBuilderCode(): string
    {
        return $this->builderCode;
    }

    /**
     * @param string $builderCode
     */
    public function setBuilderCode(string $builderCode): void
    {
        $this->builderCode = $builderCode;
    }

    public function replace(): void
    {
        if ($this->targetChanged) {
            file_put_contents($this->targetFile, $this->targetCode);
        }
        if ($this->builderChanged) {
            file_put_contents($this->builderFile, $this->builderCode);
        }
    }
}
